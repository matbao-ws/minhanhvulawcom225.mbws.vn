<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable catalog feature
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['*'],
        ]);

        // Create a test order
        $this->order = Order::query()->create([
            'order_number' => 'ORD-SHIPPING-001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john.doe@example.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '456 Le Loi, District 1, HCMC',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 500000.00,
            'discount' => 0.00,
            'grand_total' => 500000.00,
            'notes' => 'Call before delivery',
        ]);

        OrderItem::query()->create([
            'order_id' => $this->order->id,
            'product_name' => 'Test Phone',
            'sku' => 'PHONE-SKU',
            'price' => 500000.00,
            'quantity' => 1,
            'total' => 500000.00,
        ]);

        (new \Database\Seeders\AddonSeeder())->run();
        \App\Models\Addon::query()->update(['is_purchased' => true]);
    }

    public function test_guests_cannot_push_shipping(): void
    {
        $response = $this->postJson("/vi/admin/orders/{$this->order->id}/push-shipping", [
            'carrier' => 'ghtk',
            'weight' => 500,
            'province' => 'Hồ Chí Minh',
            'district' => 'Quận 1',
            'ward' => 'Phường Bến Nghé',
        ]);

        $response->assertStatus(401); // Unauthorized
    }

    public function test_unauthorized_users_cannot_push_shipping(): void
    {
        $customer = User::factory()->create([
            'role_id' => null,
        ]);
        $this->actingAs($customer);

        $response = $this->postJson("/vi/admin/orders/{$this->order->id}/push-shipping", [
            'carrier' => 'ghtk',
            'weight' => 500,
            'province' => 'Hồ Chí Minh',
            'district' => 'Quận 1',
            'ward' => 'Phường Bến Nghé',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_shipping_settings(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);
        $this->actingAs($admin);

        $ghtk = \App\Models\ShippingPartner::where('partner_code', 'DTGH000012')->first();
        $flatRate = \App\Models\ShippingPartner::where('partner_code', 'DTGHTUGIAO')->first();

        // 1. Update GHTK settings
        $response = $this->post("/vi/admin/shipping-partners/{$ghtk->id}/settings", [
            'api_token' => 'mock-token-123',
            'api_url' => 'https://services.ghtk.vn',
            'webhook_token' => 'mock-webhook-token-123',
        ]);
        $response->assertRedirect('/vi/admin/shipping-partners');

        // Toggle status to active
        $response = $this->post("/vi/admin/shipping-partners/{$ghtk->id}/toggle-status");
        $response->assertOk();

        // 2. Update Flat Rate settings
        $response = $this->post("/vi/admin/shipping-partners/{$flatRate->id}/settings", [
            'fee' => 35000,
        ]);
        $response->assertRedirect('/vi/admin/shipping-partners');

        // Toggle status to active
        $response = $this->post("/vi/admin/shipping-partners/{$flatRate->id}/toggle-status");
        $response->assertOk();

        // Verify values
        $ghtk->refresh();
        $flatRate->refresh();

        $this->assertEquals('active', $ghtk->status);
        $this->assertEquals('mock-token-123', $ghtk->settings['api_token']);
        $this->assertEquals('https://services.ghtk.vn', $ghtk->settings['api_url']);

        $this->assertEquals('active', $flatRate->status);
        $this->assertEquals(35000, $flatRate->settings['fee']);
    }

    public function test_push_shipping_fails_when_ghtk_disabled(): void
    {
        \App\Models\ShippingPartner::where('partner_code', 'DTGH000012')
            ->first()
            ->update([
                'status' => 'inactive',
            ]);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);
        $this->actingAs($admin);

        $response = $this->postJson("/vi/admin/orders/{$this->order->id}/push-shipping", [
            'carrier' => 'ghtk',
            'weight' => 500,
            'province' => 'Hồ Chí Minh',
            'district' => 'Quận 1',
            'ward' => 'Phường Bến Nghé',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Phương thức Giao Hàng Tiết Kiệm chưa được kích hoạt.'
        ]);
    }

    public function test_push_shipping_succeeds_with_mock_token(): void
    {
        // GHTK enabled with 'mock' token
        \App\Models\ShippingPartner::where('partner_code', 'DTGH000012')
            ->first()
            ->update([
                'status' => 'active',
                'settings' => [
                    'api_token' => 'mock-test-token',
                    'api_url' => 'https://services.ghtk.vn',
                ]
            ]);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);
        $this->actingAs($admin);

        $response = $this->postJson("/vi/admin/orders/{$this->order->id}/push-shipping", [
            'carrier' => 'ghtk',
            'weight' => 600,
            'province' => 'Hồ Chí Minh',
            'district' => 'Quận 1',
            'ward' => 'Phường Bến Nghé',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'tracking_number',
            'fee'
        ]);

        $this->order->refresh();
        $this->assertEquals('ghtk', $this->order->shipping_carrier);
        $this->assertNotNull($this->order->tracking_number);
        $this->assertStringContainsString('GHTK.MOCK.', $this->order->tracking_number);
        $this->assertEquals(35000, $this->order->shipping_fee);
        $this->assertEquals('processing', $this->order->status);
    }

    public function test_push_shipping_validation_errors(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);
        $this->actingAs($admin);

        // Missing required inputs
        $response = $this->postJson("/vi/admin/orders/{$this->order->id}/push-shipping", [
            'carrier' => 'invalid-carrier',
            'weight' => -10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['carrier', 'weight', 'province', 'district', 'ward']);
    }
}
