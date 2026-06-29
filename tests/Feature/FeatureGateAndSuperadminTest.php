<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureGateAndSuperadminTest extends TestCase
{
    use RefreshDatabase;

    private Role $superadminRole;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadminRole = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
        ]);

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['*'],
        ]);

        // Seed some feature settings
        FeatureSetting::query()->create([
            'feature_code' => 'multi_admin',
            'is_enabled' => true,
        ]);

        FeatureSetting::query()->create([
            'feature_code' => 'max_products',
            'is_enabled' => true,
            'limit_value' => '50',
        ]);
    }

    public function test_guest_cannot_access_features_settings(): void
    {
        $response = $this->get('/vi/admin/features');
        $response->assertRedirect('/login');
    }

    public function test_standard_admin_cannot_access_features_settings(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/features');
        $response->assertStatus(403);
    }

    public function test_superadmin_can_access_features_settings(): void
    {
        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($superadmin);

        $response = $this->get('/vi/admin/features');
        $response->assertOk();
        $response->assertViewIs('admin.features.index');
        $response->assertViewHas('features');
    }

    public function test_superadmin_can_update_features_settings(): void
    {
        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($superadmin);

        $response = $this->post('/vi/admin/features', [
            'features' => [
                'multi_admin' => '0',
                'max_products' => '1',
            ],
            'limits' => [
                'max_products' => '100',
            ]
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('feature_settings', [
            'feature_code' => 'multi_admin',
            'is_enabled' => false,
        ]);

        $this->assertDatabaseHas('feature_settings', [
            'feature_code' => 'max_products',
            'is_enabled' => true,
            'limit_value' => '100',
        ]);
    }

    public function test_admin_cannot_access_users_management_when_multi_admin_disabled(): void
    {
        // Disable multi_admin
        FeatureSetting::query()->where('feature_code', 'multi_admin')->update([
            'is_enabled' => false,
        ]);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/users');
        $response->assertStatus(403);
    }

    public function test_superadmin_can_access_users_management_even_when_multi_admin_disabled(): void
    {
        // Disable multi_admin
        FeatureSetting::query()->where('feature_code', 'multi_admin')->update([
            'is_enabled' => false,
        ]);

        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($superadmin);

        $response = $this->get('/vi/admin/users');
        $response->assertOk(); // Bypassed
    }

    public function test_standard_admin_cannot_see_superadmin_users_in_listing(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/users');
        $response->assertOk();
        
        // Assert superadmin is NOT in the view data 'users' list
        $usersList = $response->viewData('users');
        $this->assertFalse($usersList->contains($superadmin));
        $this->assertTrue($usersList->contains($admin));
    }

    public function test_standard_admin_cannot_edit_superadmin_user(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($admin);

        $responseGet = $this->get('/vi/admin/users/' . $superadmin->id . '/edit');
        $responseGet->assertStatus(403);

        $responsePut = $this->put('/vi/admin/users/' . $superadmin->id, [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.com',
            'role_id' => $this->adminRole->id,
        ]);
        $responsePut->assertStatus(403);
    }

    public function test_user_without_admin_role_cannot_access_admin_panel(): void
    {
        // User with no role_id (e.g. regular customer)
        $customer = User::factory()->create([
            'role_id' => null,
        ]);

        $this->actingAs($customer);

        $response = $this->get('/vi/admin');
        $response->assertStatus(403);
    }

    public function test_superadmin_can_toggle_feature_via_ajax(): void
    {
        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($superadmin);

        // Initially multi_admin is true
        $this->assertTrue(\App\Models\FeatureSetting::where('feature_code', 'multi_admin')->first()->is_enabled);

        $response = $this->postJson('/vi/admin/features/toggle', [
            'feature_code' => 'multi_admin',
            'is_enabled' => 0
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true
        ]);

        $this->assertFalse(\App\Models\FeatureSetting::where('feature_code', 'multi_admin')->first()->is_enabled);
    }
}
