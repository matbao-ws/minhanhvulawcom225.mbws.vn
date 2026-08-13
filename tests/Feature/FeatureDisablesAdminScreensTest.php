<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Turning a feature off must actually remove its admin surface: both the route
 * and the sidebar entry that leads to it.
 *
 * Shipping and payment configuration used to carry no feature check at all — an
 * admin with every feature disabled still reached and used both screens.
 */
class FeatureDisablesAdminScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        foreach (config('features.codes', []) as $code) {
            // "shipping" already exists: its migration seeds it enabled so the
            // screen does not vanish from sites installed before that code.
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $code],
                ['is_enabled' => true],
            );
        }

        $role = Role::query()->create([
            'name' => 'Admin',
            'permissions' => Permission::query()->pluck('code')->all(),
        ]);

        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function disable(string ...$codes): void
    {
        FeatureSetting::query()->whereIn('feature_code', $codes)->update(['is_enabled' => false]);
    }

    private function sidebar(): string
    {
        return $this->actingAs($this->admin)->get('/vi/admin')->getContent();
    }

    public function test_shipping_is_a_configurable_feature(): void
    {
        $this->assertContains('shipping', config('features.codes'));
    }

    public function test_disabling_shipping_closes_the_shipping_configuration(): void
    {
        $this->disable('shipping');

        $this->actingAs($this->admin)
            ->get('/vi/admin/shipping-partners')
            ->assertRedirect(route('admin.dashboard', ['locale' => 'vi']));

        $this->actingAs($this->admin)
            ->get('/vi/admin/shipping-partners/create')
            ->assertRedirect(route('admin.dashboard', ['locale' => 'vi']));

        $this->assertStringNotContainsString('/admin/shipping-partners', $this->sidebar());
    }

    public function test_shipping_configuration_stays_open_while_the_feature_is_on(): void
    {
        $this->actingAs($this->admin)->get('/vi/admin/shipping-partners')->assertOk();
        $this->assertStringContainsString('/admin/shipping-partners', $this->sidebar());
    }

    public function test_disabling_both_payment_features_closes_payment_configuration(): void
    {
        $this->disable('cod_order', 'online_payment');

        $this->actingAs($this->admin)
            ->get('/vi/admin/payment-methods')
            ->assertRedirect(route('admin.dashboard', ['locale' => 'vi']));

        $this->assertStringNotContainsString('/admin/payment-methods', $this->sidebar());
    }

    public function test_payment_configuration_survives_while_either_payment_feature_is_on(): void
    {
        // COD alone is still a payment method worth configuring.
        $this->disable('online_payment');
        $this->actingAs($this->admin)->get('/vi/admin/payment-methods')->assertOk();
        $this->assertStringContainsString('/admin/payment-methods', $this->sidebar());

        FeatureSetting::query()->where('feature_code', 'online_payment')->update(['is_enabled' => true]);
        $this->disable('cod_order');
        $this->actingAs($this->admin)->get('/vi/admin/payment-methods')->assertOk();
    }

    public function test_disabling_shipping_blocks_pushing_a_waybill(): void
    {
        $order = \App\Models\Order::query()->create([
            'order_number' => 'ORD-FEATURE-001',
            'customer_name' => 'Nguyen Van A',
            'customer_email' => 'a@example.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '1 Le Loi, HCMC',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000.00,
            'discount' => 0.00,
            'grand_total' => 100000.00,
        ]);

        $this->disable('shipping');

        $this->actingAs($this->admin)
            ->post("/vi/admin/orders/{$order->id}/push-shipping", [
                'carrier' => 'ghtk',
                'weight' => 500,
            ])
            ->assertRedirect(route('admin.dashboard', ['locale' => 'vi']));
    }

    public function test_superadmin_keeps_its_documented_bypass(): void
    {
        $this->disable(...config('features.codes'));

        $superadmin = User::factory()->create([
            'role_id' => Role::query()->create([
                'name' => 'Superadmin',
                'permissions' => ['*'],
                'is_system' => true,
            ])->id,
            'is_active' => true,
        ]);

        $this->actingAs($superadmin)->get('/vi/admin/shipping-partners')->assertOk();
        $this->actingAs($superadmin)->get('/vi/admin/payment-methods')->assertOk();
    }
}
