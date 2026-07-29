<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\FeatureSetting;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\ShippingPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->updateOrCreate(
            ['feature_code' => 'online_payment'],
            ['is_enabled' => true],
        );

        $role = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['manage_settings'],
        ]);
        $this->adminUser = User::factory()->create(['role_id' => $role->id]);

        (new \Database\Seeders\AddonSeeder())->run();
        PaymentMethod::query()->updateOrCreate(['method_code' => 'vnpay'], [
            'name' => 'VNPAY',
            'type' => 'connected',
            'status' => 'inactive',
            'settings' => [
                'tmn_code' => 'mock',
                'hash_secret' => 'mock',
                'api_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            ],
        ]);
    }

    public function test_admin_sees_integration_catalogue_without_prices_or_purchase_controls(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/vi/admin/addons');

        $response->assertOk();
        $response->assertSee('Tích hợp & hỗ trợ');
        $response->assertSee('liên hệ bộ phận hỗ trợ');
        $response->assertDontSee('Mua ngay');
        $response->assertDontSee('Giá bán');
    }

    public function test_addon_checkout_and_unlock_webhook_endpoints_are_removed(): void
    {
        $addon = Addon::query()->firstOrFail();

        $this->actingAs($this->adminUser)
            ->post("/vi/admin/addons/{$addon->id}/checkout")
            ->assertNotFound();
        $this->postJson('/api/public/webhooks/sepay-addon')->assertNotFound();
    }

    public function test_payment_gateway_can_be_configured_without_addon_purchase(): void
    {
        $method = PaymentMethod::query()->where('method_code', 'vnpay')->firstOrFail();

        $this->actingAs($this->adminUser)
            ->get("/vi/admin/payment-methods/{$method->id}/settings")
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->post("/vi/admin/payment-methods/{$method->id}/toggle-status")
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'active']);
    }

    public function test_shipping_partner_can_be_configured_without_addon_purchase(): void
    {
        $partner = ShippingPartner::query()->where('partner_code', 'DTGH000012')->firstOrFail();

        $this->actingAs($this->adminUser)
            ->get("/vi/admin/shipping-partners/{$partner->id}/settings")
            ->assertOk();
    }
}
