<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShippingPartner;
use App\Models\User;
use App\Services\Shipping\ShipmentDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GHN address codes travel with the order from checkout, so the shipment is one click on the order
 * page — or created automatically when an admin confirms the order, if the shop turned that on.
 */
class GhnShipmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const GHN = 'https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalog', 'cart', 'cod_order', 'shipping'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        PaymentMethod::query()->where('method_code', 'cod')->update(['status' => 'active']);
    }

    public function test_checkout_stores_the_ghn_codes_and_needs_district_and_ward_together(): void
    {
        $product = $this->product();

        $this->checkout($product, ['ghn_province_id' => 202, 'ghn_district_id' => 1442, 'ghn_ward_code' => '20109'])->assertOk();
        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame(['province_id' => 202, 'district_id' => 1442, 'ward_code' => '20109'], $order->shipping_location['ghn']);
        $this->assertSame(['province_id' => 202, 'district_id' => 1442, 'ward_code' => '20109'], $order->ghnLocation());

        $this->checkout($product, ['ghn_district_id' => 1442])->assertStatus(422);
        $this->checkout($product, ['ghn_ward_code' => '20109'])->assertStatus(422);

        $this->checkout($product)->assertOk();
        $this->assertNull(Order::query()->latest('id')->firstOrFail()->ghnLocation(), 'a form without GHN pickers still checks out');
    }

    public function test_confirming_an_order_creates_the_ghn_shipment_when_turned_on(): void
    {
        $this->ghn(autoPush: true);
        $this->fakeGhn();
        $order = $this->order(['ghn' => ['district_id' => 1442, 'ward_code' => '20109']]);

        $this->actingAs($this->admin())
            ->patch('/vi/admin/orders/'.$order->id.'/status', ['status' => 'processing', 'payment_status' => 'pending'])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Đã tạo vận đơn GHN GHN123'));

        $order->refresh();
        $this->assertSame(['processing', 'GHN123', 'ghn', 'shipping_created'], [$order->status, $order->tracking_number, $order->shipping_carrier, $order->shipping_status]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/create')
            && $r['to_district_id'] === 1442 && $r['to_ward_code'] === '20109'
            && $r['weight'] === 700 && $r['length'] === 30 && $r['client_order_code'] === $order->order_number);
    }

    public function test_nothing_is_sent_when_off_or_without_codes(): void
    {
        $this->fakeGhn();
        $admin = $this->admin();

        $this->ghn(autoPush: false);
        $withCodes = $this->order(['ghn' => ['district_id' => 1442, 'ward_code' => '20109']]);
        $this->actingAs($admin)->patch('/vi/admin/orders/'.$withCodes->id.'/status', ['status' => 'processing', 'payment_status' => 'pending'])
            ->assertSessionHas('success');
        $this->assertNull($withCodes->fresh()->tracking_number);

        $this->ghn(autoPush: true);
        $withoutCodes = $this->order(null);
        $this->actingAs($admin)->patch('/vi/admin/orders/'.$withoutCodes->id.'/status', ['status' => 'processing', 'payment_status' => 'pending'])
            ->assertSessionHas('success');
        $this->assertNull($withoutCodes->fresh()->tracking_number);

        Http::assertNothingSent();
    }

    public function test_a_failed_automatic_shipment_keeps_the_confirmation_and_can_be_retried(): void
    {
        $this->ghn(autoPush: true);
        Http::fake([self::GHN.'/available-services' => Http::response(['data' => []])]);
        $order = $this->order(['ghn' => ['district_id' => 1442, 'ward_code' => '20109']]);

        $this->actingAs($this->admin())
            ->patch('/vi/admin/orders/'.$order->id.'/status', ['status' => 'processing', 'payment_status' => 'pending'])
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'Chưa tạo được vận đơn GHN: GHN không có dịch vụ'));

        $order->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertSame('not_shipped', $order->shipping_status, 'the claim is released for a manual retry');
        $this->assertNull($order->tracking_number);
    }

    public function test_the_order_page_opens_the_dialog_filled_in(): void
    {
        $this->ghn(autoPush: false);
        $order = $this->order(['ghn' => ['district_id' => 1442, 'ward_code' => '20109']]);

        $this->actingAs($this->admin())->get('/vi/admin/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Dùng địa chỉ GHN khách chọn lúc đặt hàng')
            ->assertSee('name="to_district_id" value="1442"', false)
            ->assertSee('name="to_ward_code" value="20109"', false)
            ->assertSee('id="shipping_weight" name="weight" value="700"', false)
            ->assertSee('/vi/admin/shipping/ghn/provinces', false);
    }

    public function test_weight_comes_from_the_variants_or_the_default(): void
    {
        $order = $this->order(null);
        $dispatcher = app(ShipmentDispatcher::class);

        $this->assertSame(700, $dispatcher->weight($order, 700), 'no variant weights: the default');
    }

    public function test_settings_save_the_automatic_shipment_options(): void
    {
        $partner = $this->ghn(autoPush: false);
        Http::fake(['*/shop/all' => Http::response(['data' => ['shops' => [['_id' => 5300247]]]])]);

        $this->actingAs($this->admin())->post('/vi/admin/shipping-partners/'.$partner->id.'/settings', [
            'api_token' => 'prod-token', 'api_url' => 'https://online-gateway.ghn.vn', 'shop_id' => 5300247,
            'auto_push_on_confirm' => 1, 'default_weight' => 1200, 'default_length' => 40, 'default_width' => 30, 'default_height' => 20,
        ])->assertSessionHas('success');

        $settings = $partner->fresh()->settings;
        $this->assertTrue($settings['auto_push_on_confirm']);
        $this->assertSame([1200, 40, 30, 20], [$settings['default_weight'], $settings['default_length'], $settings['default_width'], $settings['default_height']]);
    }

    // ---------------------------------------------------------------------

    private function checkout(Product $product, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/public/orders/checkout', array_merge([
            'customer_name' => 'Khách GHN',
            'customer_email' => 'ghn'.uniqid().'@example.com',
            'customer_phone' => '0900000011',
            'shipping_address' => '12 Lê Lợi',
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $extra));
    }

    private function fakeGhn(): void
    {
        Http::fake([
            self::GHN.'/available-services' => Http::response(['data' => [['service_id' => 53321]]]),
            self::GHN.'/fee' => Http::response(['data' => ['total' => 32000]]),
            self::GHN.'/create' => Http::response(['data' => ['order_code' => 'GHN123']]),
        ]);
    }

    private function ghn(bool $autoPush): ShippingPartner
    {
        $partner = ShippingPartner::query()->where('partner_code', 'DTGH000013')->firstOrFail();
        $partner->update(['status' => 'active', 'settings' => [
            'api_token' => 'prod-token', 'api_url' => 'https://online-gateway.ghn.vn', 'shop_id' => '5300247',
            'auto_push_on_confirm' => $autoPush, 'default_weight' => 700, 'default_length' => 30, 'default_width' => 20, 'default_height' => 10,
        ]]);

        return $partner;
    }

    private function order(?array $location): Order
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-GHN-'.uniqid(),
            'customer_name' => 'Khách GHN',
            'customer_email' => 'ghn@example.com',
            'customer_phone' => '0900000011',
            'shipping_address' => '12 Lê Lợi, Phường Bến Nghé, Quận 1, Hồ Chí Minh',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000,
            'discount' => 0,
            'grand_total' => 100000,
            'shipping_location' => $location,
        ]);
        $order->items()->create(['product_name' => 'Ống nhựa', 'sku' => 'ONG-1', 'price' => 100000, 'quantity' => 1, 'total' => 100000]);

        return $order;
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => ['vi' => 'Ống nhựa'],
            'slug' => 'ong-nhua-'.str()->random(8),
            'sku' => 'ONG-'.str()->upper(str()->random(6)),
            'price' => 100000,
            'stock_quantity' => 50,
            'manage_stock' => true,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $role = Role::query()->create(['name' => 'Admin '.uniqid(), 'permissions' => ['*']]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
