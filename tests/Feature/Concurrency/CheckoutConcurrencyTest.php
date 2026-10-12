<?php

namespace Tests\Feature\Concurrency;

use App\Models\CheckoutRequest;
use App\Models\FeatureSetting;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Two processes send the same checkout, with the same Idempotency-Key, at the same moment,
 * for the last unit in stock. Exactly one order may come out of it.
 *
 * SQLite cannot show this: it has one writer and no row locks. This runs only on MySQL, in a
 * database of its own whose name ends in `_concurrency_test` (the CI job `mysql-concurrency`).
 * Its rows are committed for real, so the two processes see them: no RefreshDatabase, no
 * `--parallel` (each worker would get its own database). It cleans up after itself.
 */
#[Group('mysql')]
class CheckoutConcurrencyTest extends TestCase
{
    private const BARRIER = 'checkout_race_start';

    private ?Product $product = null;

    private string $key;

    private string $payloadFile;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql' || ! str_ends_with((string) $connection->getDatabaseName(), '_concurrency_test')) {
            $this->markTestSkipped('Needs MySQL and a dedicated *_concurrency_test database (CI job mysql-concurrency).');
        }

        foreach (['catalog', 'cart', 'cod_order'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        PaymentMethod::query()->where('method_code', 'cod')->update(['status' => 'active']);

        $this->key = (string) Str::uuid();
        $this->product = Product::query()->create([
            'name' => ['vi' => 'Chiếc cuối cùng'],
            'slug' => 'chiec-cuoi-cung-'.Str::random(8),
            'sku' => 'LAST-'.Str::upper(Str::random(6)),
            'price' => 100000,
            'stock_quantity' => 1,
            'manage_stock' => true,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->product !== null) {
            $orderIds = Order::query()->where('customer_email', $this->email())->pluck('id');
            CheckoutRequest::query()->where('idempotency_key', $this->key)->delete();
            InventoryMovement::query()->where('product_id', $this->product->id)->delete();
            Order::query()->whereIn('id', $orderIds)->delete(); // items, history, payments cascade
            $this->product->delete();
            @unlink($this->payloadFile ?? '');
        }

        parent::tearDown();
    }

    public function test_two_simultaneous_checkouts_with_one_key_make_one_order(): void
    {
        $this->payloadFile = tempnam(sys_get_temp_dir(), 'race');
        file_put_contents($this->payloadFile, json_encode([
            'barrier' => self::BARRIER,
            'key' => $this->key,
            'body' => [
                'customer_name' => 'Khách đua',
                'customer_email' => $this->email(),
                'customer_phone' => '0900000077',
                'shipping_address' => '1 Lê Lợi',
                'payment_method' => 'cod',
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            ],
        ]));

        $this->assertSame(1, (int) DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [self::BARRIER])->acquired);

        $script = base_path('tests/Support/Concurrency/checkout-race.php');
        $racers = Process::pool(fn (Pool $pool) => [
            $pool->as('a')->path(base_path())->timeout(60)->command([PHP_BINARY, $script, $this->payloadFile]),
            $pool->as('b')->path(base_path())->timeout(60)->command([PHP_BINARY, $script, $this->payloadFile]),
        ])->start();

        $this->waitUntilBothRacersWaitAtTheBarrier();
        DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::BARRIER]);

        $results = $racers->wait()->collect()->map(function ($result, $name) {
            $this->assertTrue($result->successful(), "racer {$name} crashed: ".$result->errorOutput());

            return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        });

        $this->assertSame([200, 200], $results->pluck('status')->values()->all(), json_encode($results));
        $numbers = $results->pluck('body.data.order_number')->unique()->values();
        $this->assertCount(1, $numbers, 'both racers get the same order');
        $this->assertNotNull($numbers->first());
        $this->assertSame(['true'], $results->pluck('replayed')->filter()->values()->all(), 'exactly one is the replay');

        $this->assertSame(1, Order::query()->where('customer_email', $this->email())->count());
        $this->assertSame(1, InventoryMovement::query()->where('product_id', $this->product->id)->count());
        $this->assertSame(1, CheckoutRequest::query()->where('idempotency_key', $this->key)->count());
        $this->assertSame(0, $this->product->fresh()->stock_quantity);
    }

    /** Both child processes are blocked in GET_LOCK, so the release starts them together. */
    private function waitUntilBothRacersWaitAtTheBarrier(): void
    {
        $deadline = microtime(true) + 30;
        do {
            $waiting = (int) DB::selectOne(
                "SELECT COUNT(*) AS n FROM information_schema.processlist WHERE id <> CONNECTION_ID() AND info LIKE 'SELECT GET_LOCK(%'"
            )->n;
            if ($waiting >= 2) {
                return;
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        $this->fail("only {$waiting} of 2 racers reached the barrier in 30s");
    }

    private function email(): string
    {
        return 'race-'.substr($this->key, 0, 8).'@example.com';
    }
}
