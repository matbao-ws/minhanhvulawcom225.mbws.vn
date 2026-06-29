<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\ShippingPartner;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $guestUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['manage_settings'],
        ]);

        $guestRole = Role::query()->create([
            'name' => 'Guest',
            'permissions' => [],
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->guestUser = User::factory()->create([
            'role_id' => $guestRole->id,
        ]);

        // Seed Addons
        (new \Database\Seeders\AddonSeeder())->run();

        // Seed/Update Payment methods (Stripe & VNPAY)
        PaymentMethod::query()->updateOrCreate([
            'method_code' => 'vnpay',
        ], [
            'name' => 'VNPAY',
            'type' => 'connected',
            'status' => 'inactive',
            'settings' => [
                'tmn_code' => 'mock',
                'hash_secret' => 'mock',
                'api_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            ]
        ]);

        // Seed/Update Connected Shipping Partner (GHTK)
        ShippingPartner::query()->updateOrCreate([
            'partner_code' => 'DTGH000012',
        ], [
            'name' => 'Giao Hàng Tiết Kiệm (GHTK)',
            'type' => 'connected',
            'status' => 'inactive',
            'settings' => [
                'api_token' => 'mock',
                'api_url' => 'https://mock.ghtk.vn',
            ]
        ]);
    }

    public function test_admin_can_view_addons_store(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/vi/admin/addons');
        $response->assertOk();
        $response->assertSee('Tính năng nâng cao');
        $response->assertSee('Tích hợp cổng thanh toán VNPAY');
    }

    public function test_admin_can_checkout_addon(): void
    {
        $addon = Addon::where('code', 'vnpay')->firstOrFail();

        $response = $this->actingAs($this->adminUser)->post("/vi/admin/addons/{$addon->id}/checkout");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('invoices', [
            'addon_code' => $addon->code,
            'status' => 'pending',
            'amount' => $addon->price,
        ]);
    }

    public function test_sepay_webhook_unlocks_addon(): void
    {
        $addon = Addon::where('code', 'vnpay')->firstOrFail();
        $invoice = Invoice::create([
            'invoice_number' => 'INV-ADDON-TEST1234',
            'package_name' => 'Addon: ' . $addon->name,
            'amount' => $addon->price,
            'status' => 'pending',
            'billing_date' => now(),
            'due_date' => now()->addDays(7),
            'addon_code' => $addon->code,
        ]);

        // Call webhook
        $response = $this->postJson(route('api.webhooks.sepay-addon'), [
            'id' => '11223344',
            'gateway' => 'vietinbank',
            'amount_in' => $addon->price,
            'transaction_content' => 'ADDONPAID INV-ADDON-TEST1234',
            'code' => 'ADDONPAID INV-ADDON-TEST1234',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Addon unlocked successfully.',
        ]);

        $invoice->refresh();
        $addon->refresh();

        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals('11223344', $invoice->sepay_transaction_id);
        $this->assertTrue($addon->is_purchased);
    }

    public function test_gated_payment_gateways_restricted_before_purchase(): void
    {
        // 1. Checkout with vnpay fails with 403
        $product = Product::query()->create([
            'name' => ['vi' => 'Sản phẩm test 1', 'en' => 'Test Product 1'],
            'slug' => 'san-pham-test-1',
            'sku' => 'TEST-123-1',
            'price' => 10000,
            'is_active' => true,
            'manage_stock' => false,
        ]);
        $checkoutData = [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '0987654321',
            'shipping_address' => '123 Main St',
            'payment_method' => 'vnpay',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ];

        $response = $this->postJson('/api/public/orders/checkout', $checkoutData);
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Tính năng thanh toán qua VNPAY chưa được mở khóa. Vui lòng mua Addon để sử dụng.'
        ]);

        // 2. Toggle VNPAY status fails
        $vnpayMethod = PaymentMethod::where('method_code', 'vnpay')->firstOrFail();
        $toggleResponse = $this->actingAs($this->adminUser)->post("/vi/admin/payment-methods/{$vnpayMethod->id}/toggle-status");
        $toggleResponse->assertOk();
        $toggleResponse->assertJson([
            'success' => false,
            'message' => 'Cổng thanh toán VNPAY chưa được mở khóa. Vui lòng mua Addon để sử dụng.'
        ]);

        // 3. View VNPAY settings fails
        $settingsResponse = $this->actingAs($this->adminUser)->get("/vi/admin/payment-methods/{$vnpayMethod->id}/settings");
        $settingsResponse->assertRedirect('/vi/admin/payment-methods');
        $settingsResponse->assertSessionHas('error');
    }

    public function test_gated_payment_gateways_allowed_after_purchase(): void
    {
        // Unlock VNPAY
        Addon::where('code', 'vnpay')->update(['is_purchased' => true]);

        // Activate VNPAY
        PaymentMethod::where('method_code', 'vnpay')->update(['status' => 'active']);

        // 1. Checkout with vnpay succeeds
        $product = Product::query()->create([
            'name' => ['vi' => 'Sản phẩm test 2', 'en' => 'Test Product 2'],
            'slug' => 'san-pham-test-2',
            'sku' => 'TEST-123-2',
            'price' => 10000,
            'is_active' => true,
            'manage_stock' => false,
        ]);
        $checkoutData = [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '0987654321',
            'shipping_address' => '123 Main St',
            'payment_method' => 'vnpay',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ];

        $response = $this->postJson('/api/public/orders/checkout', $checkoutData);
        $response->assertOk();
        $response->assertJsonFragment([
            'payment_method' => 'vnpay'
        ]);

        // 2. View VNPAY settings allowed
        $vnpayMethod = PaymentMethod::where('method_code', 'vnpay')->firstOrFail();
        $settingsResponse = $this->actingAs($this->adminUser)->get("/vi/admin/payment-methods/{$vnpayMethod->id}/settings");
        $settingsResponse->assertOk();
        $settingsResponse->assertViewIs('admin.payment_methods.settings');
    }

    public function test_shipping_api_restricted_before_purchase(): void
    {
        $partner = ShippingPartner::where('partner_code', 'DTGH000012')->firstOrFail();

        // 1. Toggle GHTK status fails
        $toggleResponse = $this->actingAs($this->adminUser)->post("/vi/admin/shipping-partners/{$partner->id}/toggle-status");
        $toggleResponse->assertOk();
        $toggleResponse->assertJson([
            'success' => false,
            'message' => 'Tính năng kết nối API vận chuyển chưa được mở khóa. Vui lòng mua Addon để sử dụng.'
        ]);

        // 2. View settings redirects
        $settingsResponse = $this->actingAs($this->adminUser)->get("/vi/admin/shipping-partners/{$partner->id}/settings");
        $settingsResponse->assertRedirect('/vi/admin/shipping-partners');
        $settingsResponse->assertSessionHas('error');
    }

    public function test_shipping_api_allowed_after_purchase(): void
    {
        // Unlock Shipping API
        Addon::where('code', 'shipping_api')->update(['is_purchased' => true]);

        $partner = ShippingPartner::where('partner_code', 'DTGH000012')->firstOrFail();

        // 1. View settings works
        $settingsResponse = $this->actingAs($this->adminUser)->get("/vi/admin/shipping-partners/{$partner->id}/settings");
        $settingsResponse->assertOk();
        $settingsResponse->assertViewIs('admin.shipping_partners.settings');
    }
}
