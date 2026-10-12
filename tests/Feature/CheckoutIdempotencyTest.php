<?php

namespace Tests\Feature;

use App\Jobs\SendOrderStatusEmail;
use App\Models\CheckoutRequest;
use App\Models\FeatureSetting;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\CheckoutIdempotency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /api/public/orders/checkout with an Idempotency-Key: a retry returns the order the
 * key already created, never a second one. Without a key nothing changes.
 */
class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = '7f9c2ba4-e88f-41a8-b9c7-6c1f2d3e4a5b';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalog', 'cart', 'cod_order'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        PaymentMethod::query()->where('method_code', 'cod')->update(['status' => 'active']);
        $this->product = $this->product('A');
    }

    public function test_a_retry_with_the_same_key_returns_the_same_order_once(): void
    {
        Queue::fake();
        Http::preventStrayRequests(); // nothing in checkout may call out

        $first = $this->checkout(key: self::KEY)->assertOk();
        $second = $this->checkout(key: self::KEY)->assertOk()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.order_number'), $second->json('data.order_number'));
        $this->assertNotNull($first->json('data.order_number'));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, CheckoutRequest::query()->count());
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(49, $this->product->fresh()->stock_quantity);
        Queue::assertPushed(SendOrderStatusEmail::class, 1);
        $this->assertFalse($first->headers->has('Idempotent-Replayed'));
    }

    public function test_the_fingerprint_ignores_item_order_spacing_and_case(): void
    {
        $other = $this->product('B');
        $items = [['product_id' => $this->product->id, 'quantity' => 1], ['product_id' => $other->id, 'quantity' => 2]];

        $first = $this->checkout(['items' => $items], self::KEY)->assertOk();
        $this->checkout([
            'items' => array_reverse($items),
            'customer_email' => '  KHACH@Example.com ',
            'customer_name' => ' Khách Một ',
        ], self::KEY)->assertOk()->assertJsonPath('data.order_number', $first->json('data.order_number'));

        $this->assertSame(1, Order::query()->count());
    }

    public function test_the_same_key_with_another_cart_is_a_conflict(): void
    {
        $this->checkout(key: self::KEY)->assertOk();

        $this->checkout(['items' => [['product_id' => $this->product->id, 'quantity' => 2]]], self::KEY)
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'idempotency_key_reused');
        $this->checkout(['customer_phone' => '0911111111'], self::KEY)->assertStatus(409);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(49, $this->product->fresh()->stock_quantity);
    }

    public function test_keys_are_scoped_per_customer_and_never_return_someone_elses_order(): void
    {
        $alice = User::factory()->create(['role_id' => null]);
        $bob = User::factory()->create(['role_id' => null]);

        Sanctum::actingAs($alice, ['customer']);
        $aliceOrder = $this->checkout(key: self::KEY)->assertOk()->json('data.order_number');

        Sanctum::actingAs($bob, ['customer']);
        $bobOrder = $this->checkout(key: self::KEY)->assertOk();
        $this->assertNotSame($aliceOrder, $bobOrder->json('data.order_number'));
        $this->assertFalse($bobOrder->headers->has('Idempotent-Replayed'));

        $this->assertSame(2, Order::query()->count());
        $this->assertEqualsCanonicalizing(['user:'.$alice->id, 'user:'.$bob->id], CheckoutRequest::query()->pluck('scope')->all());
    }

    public function test_a_guest_does_not_share_a_signed_in_customers_keys(): void
    {
        Sanctum::actingAs(User::factory()->create(['role_id' => null]), ['customer']);
        $customerOrder = $this->checkout(key: self::KEY)->assertOk()->json('data.order_number');

        $this->app['auth']->forgetGuards();
        $guestOrder = $this->checkout(key: self::KEY)->assertOk()->json('data.order_number');

        $this->assertNotSame($customerOrder, $guestOrder);
        $this->assertSame(2, Order::query()->count());
    }

    public function test_without_a_key_every_checkout_creates_an_order(): void
    {
        $this->checkout()->assertOk();
        $this->checkout()->assertOk();

        $this->assertSame(2, Order::query()->count());
        $this->assertSame(0, CheckoutRequest::query()->count());
    }

    public function test_malformed_and_short_guest_keys_are_rejected(): void
    {
        $this->checkout(key: 'bad key with spaces!!')->assertStatus(422)->assertJsonStructure(['errors' => [CheckoutIdempotency::HEADER]]);
        $this->checkout(key: 'short')->assertStatus(422);
        $this->checkout(key: str_repeat('a', 65))->assertStatus(422);

        $twentyChars = 'abcdefghij0123456789';
        $this->checkout(key: $twentyChars)->assertStatus(422); // a guest needs at least 32

        Sanctum::actingAs(User::factory()->create(['role_id' => null]), ['customer']);
        $this->checkout(key: $twentyChars)->assertOk(); // scoped to the account, 16 is enough

        $this->assertSame(1, Order::query()->count());
    }

    public function test_a_key_whose_order_is_gone_or_cancelled_cannot_be_reused(): void
    {
        $this->checkout(key: self::KEY)->assertOk();
        Order::query()->firstOrFail()->update(['status' => 'cancelled']);

        $this->checkout(key: self::KEY)->assertStatus(409)->assertJsonPath('errors.code', 'idempotency_key_unusable');

        Order::query()->firstOrFail()->delete();
        $this->assertNull(CheckoutRequest::query()->firstOrFail()->order_id);
        $this->checkout(key: self::KEY)->assertStatus(409)->assertJsonPath('errors.code', 'idempotency_key_unusable');

        $this->assertSame(0, Order::query()->count(), 'a dead key never creates a new order');
    }

    public function test_losing_the_race_on_the_key_rolls_back_and_returns_the_winners_order(): void
    {
        $winner = $this->checkout(key: self::KEY)->assertOk()->json('data.order_number');

        // The loser looked the key up before the winner committed, so it saw nothing
        // and went on to claim it: the unique index stops it inside the transaction.
        $this->app->instance(CheckoutIdempotency::class, new class extends CheckoutIdempotency
        {
            private bool $looked = false;

            public function find(string $scope, string $key): ?CheckoutRequest
            {
                if (! $this->looked) {
                    $this->looked = true;

                    return null;
                }

                return parent::find($scope, $key);
            }
        });

        $this->checkout(key: self::KEY)->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.order_number', $winner);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(49, $this->product->fresh()->stock_quantity);
    }

    private function checkout(array $extra = [], ?string $key = null): TestResponse
    {
        $headers = $key !== null ? [CheckoutIdempotency::HEADER => $key] : [];

        return $this->postJson('/api/public/orders/checkout', array_merge([
            'customer_name' => 'Khách Một',
            'customer_email' => 'khach@example.com',
            'customer_phone' => '0900000001',
            'shipping_address' => '12 Lê Lợi',
            'payment_method' => 'cod',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $extra), $headers);
    }

    private function product(string $suffix): Product
    {
        return Product::query()->create([
            'name' => ['vi' => 'Sản phẩm '.$suffix],
            'slug' => 'san-pham-'.strtolower($suffix).'-'.str()->random(6),
            'sku' => 'SP-'.$suffix.'-'.str()->upper(str()->random(6)),
            'price' => 100000,
            'stock_quantity' => 50,
            'manage_stock' => true,
            'is_active' => true,
        ]);
    }
}
