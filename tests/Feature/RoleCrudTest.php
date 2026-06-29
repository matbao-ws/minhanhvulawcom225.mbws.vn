<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleCrudTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->create([
            'feature_code' => 'multi_admin',
            'is_enabled' => true,
        ]);

        $this->adminRole = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
        ]);
    }

    public function test_guests_cannot_access_roles(): void
    {
        $response = $this->get('/vi/admin/roles');
        $response->assertRedirect('/login');
    }

    public function test_non_admins_cannot_access_roles(): void
    {
        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer);

        $response = $this->get('/vi/admin/roles');
        $response->assertStatus(403);
    }

    public function test_admin_can_browse_roles(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        Role::create([
            'name' => 'Editor',
            'permissions' => ['manage_posts'],
        ]);

        $response = $this->get('/vi/admin/roles');
        $response->assertOk();
        $response->assertSee('Editor');
    }

    public function test_admin_can_create_role_via_form(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        $response = $this->post('/vi/admin/roles', [
            'name' => 'Product Manager',
            'permissions' => ['manage_products', 'manage_orders'],
        ]);

        $response->assertRedirect('/vi/admin/roles');
        
        $this->assertDatabaseHas('roles', [
            'name' => 'Product Manager',
        ]);

        $role = Role::where('name', 'Product Manager')->first();
        $this->assertContains('manage_products', $role->permissions);
        $this->assertContains('manage_orders', $role->permissions);
    }

    public function test_admin_can_update_role(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        $role = Role::create([
            'name' => 'Staff',
            'permissions' => ['manage_vouchers'],
        ]);

        $response = $this->put("/vi/admin/roles/{$role->id}", [
            'name' => 'Senior Staff',
            'permissions' => ['manage_vouchers', 'manage_orders'],
        ]);

        $response->assertRedirect('/vi/admin/roles');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Senior Staff',
        ]);

        $this->assertContains('manage_orders', $role->fresh()->permissions);
    }

    public function test_admin_can_delete_unused_role(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        $role = Role::create([
            'name' => 'Unused Role',
            'permissions' => [],
        ]);

        $response = $this->delete("/vi/admin/roles/{$role->id}");
        $response->assertRedirect('/vi/admin/roles');

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_admin_cannot_delete_role_in_use(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        $role = Role::create([
            'name' => 'In Use Role',
            'permissions' => [],
        ]);

        // Assign a user to this role
        User::factory()->create(['role_id' => $role->id]);

        $response = $this->delete("/vi/admin/roles/{$role->id}");
        $response->assertRedirect('/vi/admin/roles');
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_user_without_manage_roles_permission_cannot_access_roles(): void
    {
        $roleWithoutRolesPermission = Role::create([
            'name' => 'Staff',
            'permissions' => ['manage_products'],
        ]);
        $staff = User::factory()->create(['role_id' => $roleWithoutRolesPermission->id]);
        $this->actingAs($staff);

        $response = $this->get('/vi/admin/roles');
        $response->assertStatus(403);
    }

    public function test_non_superadmin_cannot_see_or_manage_superadmin_role(): void
    {
        $nonSuperadminRole = Role::create([
            'name' => 'Regular Admin',
            'permissions' => ['manage_users', 'manage_roles'],
        ]);
        $admin = User::factory()->create(['role_id' => $nonSuperadminRole->id]);
        $this->actingAs($admin);

        // 1. Should not be able to access roles index
        $response = $this->get('/vi/admin/roles');
        $response->assertStatus(403);

        // 2. Should get 403 on edit/update/delete Superadmin role
        $superadminRole = Role::where('name', 'Superadmin')->first();

        $responseEdit = $this->get("/vi/admin/roles/{$superadminRole->id}/edit");
        $responseEdit->assertStatus(403);

        $responseUpdate = $this->put("/vi/admin/roles/{$superadminRole->id}", [
            'name' => 'Superadmin Hack',
            'permissions' => ['*'],
        ]);
        $responseUpdate->assertStatus(403);

        $responseDelete = $this->delete("/vi/admin/roles/{$superadminRole->id}");
        $responseDelete->assertStatus(403);
    }
}
