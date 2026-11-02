<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingPartner;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\ShippingService;
use App\Support\Shipping\ShippingProfileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The shipping weight of a product (simple products included): saved from the admin form, imported and
 * exported, inherited by variants, and used by every carrier through ShippingProfileResolver.
 */
class ProductShippingWeightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->updateOrCreate(['feature_code' => 'catalog'], ['is_enabled' => true]);
    }

    public function test_admin_saves_clears_and_validates_the_weight_of_a_simple_product(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/vi/admin/products', ['name' => 'Cát mèo', 'slug' => 'cat-meo', 'sku' => 'CAT-1', 'price' => 90000, 'weight_grams' => 5000, 'is_active' => 1])
            ->assertRedirect();
        $product = Product::query()->where('sku', 'CAT-1')->firstOrFail();
        $this->assertSame(5000, $product->weight_grams);

        $this->put("/vi/admin/products/{$product->id}", ['name' => 'Cát mèo', 'slug' => 'cat-meo', 'sku' => 'CAT-1', 'price' => 90000, 'weight_grams' => 5500, 'is_active' => 1])
            ->assertRedirect();
        $this->assertSame(5500, $product->fresh()->weight_grams);

        // A blank field clears it; a missing field (an API or script that never sends it) keeps it.
        $this->put("/vi/admin/products/{$product->id}", ['name' => 'Cát mèo', 'slug' => 'cat-meo', 'sku' => 'CAT-1', 'price' => 90000, 'weight_grams' => '', 'is_active' => 1])
            ->assertRedirect();
        $this->assertNull($product->fresh()->weight_grams);

        $product->update(['weight_grams' => 700]);
        app(ProductService::class)->update($product->fresh(), ['price' => 95000]);
        $this->assertSame(700, $product->fresh()->weight_grams);

        foreach ([-1, 50001, 'nặng'] as $bad) {
            $this->from('/vi/admin/products/create')
                ->post('/vi/admin/products', ['name' => 'X', 'slug' => 'x-'.md5((string) $bad), 'price' => 1, 'weight_grams' => $bad])
                ->assertSessionHasErrors('weight_grams');
        }
    }

    public function test_the_product_form_shows_the_weight_field_with_its_meaning(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::query()->create(['name' => ['vi' => 'Hạt'], 'slug' => 'hat', 'price' => 1, 'weight_grams' => 1200, 'is_active' => true]);

        $this->get("/vi/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertSee('name="weight_grams"', false)
            ->assertSee('value="1200"', false)
            ->assertSee(__('catalog.products.help.weight'));
    }

    public function test_the_resolver_prefers_the_variant_then_the_product_and_treats_zero_as_missing(): void
    {
        $resolver = app(ShippingProfileResolver::class);
        $product = new Product(['weight_grams' => 400]);

        $this->assertSame(250, $resolver->forVariant(new ProductVariant(['weight_grams' => 250]), $product)->weightGrams);
        $this->assertSame(400, $resolver->forVariant(new ProductVariant(['weight_grams' => null]), $product)->weightGrams);
        $this->assertSame(400, $resolver->forVariant(new ProductVariant(['weight_grams' => 0]), $product)->weightGrams);
        $this->assertSame(400, $resolver->forVariant(null, $product)->weightGrams);
        $this->assertFalse($resolver->forVariant(null, new Product(['weight_grams' => 0]))->hasWeight());
        $this->assertFalse($resolver->forVariant(null, null)->hasWeight());
    }

    public function test_the_parcel_weight_is_the_sum_when_every_line_is_known_and_the_default_otherwise(): void
    {
        [$order, $items] = $this->orderWith([['weight' => 300, 'qty' => 2], ['weight' => 1000, 'qty' => 1]]);
        $resolver = app(ShippingProfileResolver::class);

        $this->assertSame(1600, $resolver->parcelWeight($order->fresh(), 500));

        // One line without a weight: a partial sum would under-declare, so the shop default is used.
        [$partial] = $this->orderWith([['weight' => 300, 'qty' => 1], ['weight' => null, 'qty' => 1]]);
        $this->assertSame(500, $resolver->parcelWeight($partial->fresh(), 500));

        [$heavy] = $this->orderWith([['weight' => 40000, 'qty' => 3]]);
        $this->assertSame(ShippingProfileResolver::MAX_PARCEL_GRAMS, $resolver->parcelWeight($heavy->fresh(), 500));
    }

    public function test_unknown_lines_share_what_is_left_of_the_confirmed_parcel_weight(): void
    {
        [$order, $items] = $this->orderWith([['weight' => 300, 'qty' => 2], ['weight' => null, 'qty' => 2]]);

        $kg = app(ShippingProfileResolver::class)->unitKilograms($order->fresh(), 1000);

        $this->assertSame(0.3, $kg[$items[0]->id]);
        $this->assertSame(0.2, $kg[$items[1]->id]); // (1000 - 600) / 2 units
    }

    public function test_ghtk_receives_each_products_real_weight_and_the_total(): void
    {
        ShippingPartner::query()->where('partner_code', 'DTGH000012')->update([
            'status' => 'active',
            'settings' => [
                'api_token' => 'real-token',
                'api_url' => 'https://services.ghtk.vn',
                'pickup' => ['name' => 'Shop', 'tel' => '0900000000', 'province' => 'Hồ Chí Minh', 'district' => 'Quận 1', 'ward' => 'Bến Nghé', 'address' => '1 Lê Lợi'],
            ],
        ]);
        Http::fake(['services.ghtk.vn/*' => Http::response(['success' => true, 'order' => ['label' => 'S1.A2', 'fee' => 22000]])]);
        [$order, $items] = $this->orderWith([['weight' => 300, 'qty' => 2], ['weight' => null, 'qty' => 1]]);

        $result = app(ShippingService::class)->pushToGHTK($order->fresh(), ['weight' => 1000]);

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) use ($items) {
            $payload = $request->data();

            return $payload['order']['total_weight'] === 1000
                && $payload['products'][0]['weight'] === 0.3
                && $payload['products'][1]['weight'] === 0.4 // the line with no weight takes what is left of the parcel
                && $payload['order']['weight_option'] === 'gram'
                && $items[0]->product_name === $payload['products'][0]['name'];
        });
    }

    public function test_the_standard_csv_round_trips_the_weight_and_a_file_without_the_column_keeps_it(): void
    {
        $this->actingAs(User::factory()->create());
        $csv = "Name_VI,Name_EN,Slug,SKU,Price,Weight_Grams\nCát,,cat,CAT-9,100,2500\n";
        $this->importCsv($csv, 'standard');
        $this->assertSame(2500, Product::query()->where('sku', 'CAT-9')->firstOrFail()->weight_grams);

        $export = $this->get('/vi/admin/products/export')->streamedContent();
        $this->assertStringContainsString('weight_grams', $export);
        $this->assertStringContainsString(',2500,', $export);

        $this->importCsv("Name_VI,Name_EN,Slug,SKU,Price\nCát,,cat,CAT-9,150\n", 'standard');
        $product = Product::query()->where('sku', 'CAT-9')->firstOrFail();
        $this->assertEquals(150, $product->price);
        $this->assertSame(2500, $product->weight_grams);

        $this->importCsv("Name_VI,Name_EN,Slug,SKU,Price,Weight_Grams\nCát,,cat,CAT-9,150,\n", 'standard');
        $this->assertNull($product->fresh()->weight_grams);
    }

    public function test_the_woocommerce_weight_in_kilograms_becomes_grams_and_a_bad_value_fails_only_its_row(): void
    {
        $this->actingAs(User::factory()->create());
        $csv = "SKU,Name,Published,Regular price,Weight (kg)\nWC-1,Hạt,1,100,\"1,5\"\nWC-2,Cát,1,100,0.25\nWC-3,Sai,1,100,abc\nWC-4,Nặng,1,100,80\n";

        $response = $this->importCsv($csv, 'wordpress');

        $this->assertSame(1500, Product::query()->where('sku', 'WC-1')->firstOrFail()->weight_grams);
        $this->assertSame(250, Product::query()->where('sku', 'WC-2')->firstOrFail()->weight_grams);
        $this->assertDatabaseMissing('products', ['sku' => 'WC-3']);
        $this->assertDatabaseMissing('products', ['sku' => 'WC-4']);
        $response->assertSessionHas('import_errors');
    }

    public function test_duplicating_a_product_copies_its_weight(): void
    {
        $original = app(ProductService::class)->create(['name' => ['vi' => 'Hạt', 'en' => 'Food'], 'sku' => 'HAT-1', 'price' => 10, 'weight_grams' => 900, 'is_active' => true]);

        $copy = app(ProductService::class)->duplicate($original);

        $this->assertSame(900, $copy->weight_grams);
    }

    /**
     * @param  list<array{weight: ?int, qty: int}>  $lines  a null weight is a product with no weight entered
     * @return array{0: Order, 1: list<OrderItem>}
     */
    private function orderWith(array $lines): array
    {
        static $n = 0;
        $n++;
        $order = Order::query()->create([
            'order_number' => 'ORD-W-'.$n,
            'customer_name' => 'An',
            'customer_email' => 'an@example.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '1 Lê Lợi, Q1',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000,
            'discount' => 0,
            'grand_total' => 100000,
        ]);
        $items = [];
        foreach ($lines as $i => $line) {
            $product = Product::query()->create(['name' => ['vi' => "SP {$n}-{$i}"], 'slug' => "sp-{$n}-{$i}", 'price' => 1000, 'weight_grams' => $line['weight'], 'is_active' => true]);
            $items[] = OrderItem::query()->create([
                'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => "SP {$n}-{$i}",
                'sku' => "SKU-{$n}-{$i}", 'price' => 1000, 'quantity' => $line['qty'], 'total' => 1000 * $line['qty'],
            ]);
        }

        return [$order, $items];
    }

    private function importCsv(string $csv, string $type)
    {
        $path = tempnam(sys_get_temp_dir(), 'weight_import');
        file_put_contents($path, $csv);

        return $this->post('/vi/admin/products/import', [
            'import_type' => $type,
            'import_file' => new UploadedFile($path, 'import.csv', 'text/csv', null, true),
        ]);
    }
}
