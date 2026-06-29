<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\ShippingPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingPartnerTest extends TestCase
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

        (new \Database\Seeders\AddonSeeder())->run();
        \App\Models\Addon::query()->update(['is_purchased' => true]);
    }

    public function test_guests_cannot_access_shipping_partners(): void
    {
        $response = $this->get('/vi/admin/shipping-partners');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_users_cannot_access_shipping_partners(): void
    {
        $response = $this->actingAs($this->guestUser)->get('/vi/admin/shipping-partners');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_shipping_partners_list(): void
    {
        // Seed a shipping partner
        ShippingPartner::query()->create([
            'partner_code' => 'DTGHTEST01',
            'name' => 'Giao Hang Test Partner',
            'type' => 'custom',
            'status' => 'inactive',
            'settings' => ['fee' => 15000],
        ]);

        $response = $this->actingAs($this->adminUser)->get('/vi/admin/shipping-partners');

        $response->assertOk();
        $response->assertViewIs('admin.shipping_partners.index');
        $response->assertSee('Giao Hang Test Partner');
        $response->assertSee('DTGHTEST01');
    }

    public function test_admin_can_create_custom_shipping_partner(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/vi/admin/shipping-partners', [
            'name' => 'Tự giao hàng ngoại tỉnh',
            'fee' => 45000,
            'phone' => '0987654321',
            'account_name' => 'Driver Account Info',
        ]);

        $response->assertRedirect('/vi/admin/shipping-partners');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shipping_partners', [
            'name' => 'Tự giao hàng ngoại tỉnh',
            'type' => 'custom',
            'phone' => '0987654321',
            'account_name' => 'Driver Account Info',
        ]);

        $partner = ShippingPartner::where('name', 'Tự giao hàng ngoại tỉnh')->first();
        $this->assertEquals(45000, $partner->settings['fee']);
    }

    public function test_admin_can_toggle_shipping_partner_status(): void
    {
        $partner = ShippingPartner::query()->create([
            'partner_code' => 'DTGHTEST02',
            'name' => 'Toggle Status Partner',
            'type' => 'custom',
            'status' => 'inactive',
            'settings' => ['fee' => 15000],
        ]);

        $response = $this->actingAs($this->adminUser)->post("/vi/admin/shipping-partners/{$partner->id}/toggle-status");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'active',
        ]);

        $partner->refresh();
        $this->assertEquals('active', $partner->status);
    }

    public function test_admin_can_update_connected_shipping_partner_settings(): void
    {
        $partner = ShippingPartner::where('partner_code', 'DTGH000012')->firstOrFail();

        $response = $this->actingAs($this->adminUser)->post("/vi/admin/shipping-partners/{$partner->id}/settings", [
            'api_token' => 'mock-token-xyz',
            'api_url' => 'https://services.ghtk.vn',
            'webhook_token' => 'custom-webhook-secret',
        ]);

        $response->assertRedirect('/vi/admin/shipping-partners');
        $response->assertSessionHas('success');

        $partner->refresh();
        $this->assertEquals('mock-token-xyz', $partner->settings['api_token']);
        $this->assertEquals('https://services.ghtk.vn', $partner->settings['api_url']);
        $this->assertEquals('custom-webhook-secret', $partner->settings['webhook_token']);
    }
}
