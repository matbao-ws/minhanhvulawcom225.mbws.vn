<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\PaymentMethod;
use App\Models\FeatureSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $guestUser;

    protected function setUp(): void
    {
        parent::setUp();

        // A shop that accepts both. COD is what keeps the payment configuration
        // screen open in the test that switches online payment off.
        foreach (['online_payment', 'cod_order'] as $featureCode) {
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $featureCode],
                ['is_enabled' => true],
            );
        }

        $adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['settings.view', 'settings.update', 'shipping.view', 'shipping.create', 'shipping.update', 'shipping.delete', 'payments.view', 'payments.create', 'payments.update', 'payments.delete'],
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

        // Reset or seed Stripe for testing (normally seeded by migrations)
        $stripe = PaymentMethod::where('method_code', 'stripe')->first();
        if ($stripe) {
            $stripe->update([
                'status' => 'inactive',
                'settings' => [
                    'publishable_key' => '',
                    'secret_key' => '',
                    'webhook_secret' => '',
                ]
            ]);
        } else {
            PaymentMethod::query()->create([
                'method_code' => 'stripe',
                'name' => 'Cổng thanh toán quốc tế Stripe',
                'type' => 'connected',
                'status' => 'inactive',
                'settings' => [
                    'publishable_key' => '',
                    'secret_key' => '',
                    'webhook_secret' => '',
                ]
            ]);
        }

    }

    public function test_guests_cannot_access_payment_methods(): void
    {
        $response = $this->get('/vi/admin/payment-methods');
        $response->assertRedirect('/vi/admin/login');
    }

    public function test_unauthorized_users_cannot_access_payment_methods(): void
    {
        $response = $this->actingAs($this->guestUser)->get('/vi/admin/payment-methods');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_payment_methods_list(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/vi/admin/payment-methods');

        $response->assertOk();
        $response->assertViewIs('admin.payment_methods.index');
        $response->assertSee('Cổng thanh toán quốc tế Stripe');
    }

    public function test_admin_can_create_custom_payment_method(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/vi/admin/payment-methods', [
            'name' => 'Chuyển khoản ViettelPay',
            'description' => 'Chuyển khoản đến số điện thoại 0987654321',
        ]);

        $response->assertRedirect('/vi/admin/payment-methods');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payment_methods', [
            'name' => 'Chuyển khoản ViettelPay',
            'type' => 'custom',
        ]);

        $method = PaymentMethod::where('name', 'Chuyển khoản ViettelPay')->first();
        $this->assertEquals('Chuyển khoản đến số điện thoại 0987654321', $method->settings['description']);
    }

    public function test_admin_cannot_toggle_unconfigured_payment_method(): void
    {
        $method = PaymentMethod::where('method_code', 'stripe')->firstOrFail();

        $response = $this->actingAs($this->adminUser)->post("/vi/admin/payment-methods/{$method->id}/toggle-status");

        $response->assertOk();
        $response->assertJson([
            'success' => false,
        ]);

        $method->refresh();
        $this->assertEquals('inactive', $method->status);
    }

    public function test_admin_cannot_activate_an_unimplemented_payment_gateway(): void
    {
        $method = PaymentMethod::where('method_code', 'stripe')->firstOrFail();
        $method->update([
            'settings' => [
                'publishable_key' => 'pk_test_123',
                'secret_key' => 'sk_test_123',
            ]
        ]);

        $response = $this->actingAs($this->adminUser)->post("/vi/admin/payment-methods/{$method->id}/toggle-status");

        $response->assertOk();
        $response->assertJson([
            'success' => false,
        ]);

        $method->refresh();
        $this->assertEquals('inactive', $method->status);
    }

    public function test_admin_can_update_payment_method_settings(): void
    {
        $method = PaymentMethod::where('method_code', 'stripe')->firstOrFail();

        $response = $this->actingAs($this->adminUser)->post("/vi/admin/payment-methods/{$method->id}/settings", [
            'publishable_key' => 'pk_live_123',
            'secret_key' => 'sk_live_123',
            'webhook_secret' => 'whsec_123',
        ]);

        $response->assertRedirect('/vi/admin/payment-methods');
        $response->assertSessionHas('success');

        $method->refresh();
        $this->assertEquals('pk_live_123', $method->settings['publishable_key']);
        $this->assertEquals('sk_live_123', $method->settings['secret_key']);
        $this->assertEquals('whsec_123', $method->settings['webhook_secret']);
    }

    public function test_disabled_online_payment_is_hidden_and_blocked_for_regular_admin(): void
    {
        FeatureSetting::query()->where('feature_code', 'online_payment')->update(['is_enabled' => false]);
        $method = PaymentMethod::query()->where('method_code', 'stripe')->firstOrFail();

        $this->actingAs($this->adminUser)
            ->get('/vi/admin/payment-methods')
            ->assertOk()
            ->assertDontSee('Cổng thanh toán quốc tế Stripe');

        $this->actingAs($this->adminUser)
            ->get("/vi/admin/payment-methods/{$method->id}/settings")
            ->assertForbidden();
    }

    public function test_superadmin_can_manage_online_payment_while_feature_is_disabled(): void
    {
        FeatureSetting::query()->where('feature_code', 'online_payment')->update(['is_enabled' => false]);
        $role = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $superadmin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($superadmin)
            ->get('/vi/admin/payment-methods')
            ->assertOk()
            ->assertSee('Cổng thanh toán quốc tế Stripe');
    }
}
