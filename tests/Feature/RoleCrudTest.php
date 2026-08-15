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

    private Role $superadminRole;

    protected function setUp(): void
    {
        parent::setUp();

        (new \Database\Seeders\PermissionSeeder())->run();

        FeatureSetting::query()->create([
            'feature_code' => 'multi_admin',
            'is_enabled' => true,
        ]);

        $this->superadminRole = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
        ]);
    }

    private function superadmin(): User
    {
        return User::factory()->create(['role_id' => $this->superadminRole->id]);
    }

    public function test_guests_cannot_access_roles(): void
    {
        $response = $this->get('/vi/admin/roles');
        $response->assertRedirect('/vi/admin/login');
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
        $this->actingAs($this->superadmin());

        Role::create([
            'name' => 'Editor',
            'permissions' => ['posts.view', 'posts.update'],
        ]);

        $response = $this->get('/vi/admin/roles');
        $response->assertOk();
        $response->assertSee('Editor');
    }

    /**
     * The matrix keys every switch by module name, so a rendering bug that
     * replaces those keys with list indexes would silently produce
     * `permissions[]=0.view`.
     */
    public function test_permission_matrix_renders_switches_keyed_by_module(): void
    {
        $this->actingAs($this->superadmin());
        $role = Role::create(['name' => 'Editor', 'permissions' => ['posts.view']]);

        $this->get('/vi/admin/roles/create')
            ->assertOk()
            ->assertSee('perm_products_view', false)
            ->assertSee('value="orders.update"', false);

        $this->get("/vi/admin/roles/{$role->id}/edit")
            ->assertOk()
            ->assertSee('perm_posts_view', false);
    }

    public function test_the_form_only_offers_permissions_the_actor_can_grant(): void
    {
        $managerRole = Role::create([
            'name' => 'Permission Manager',
            'permissions' => ['roles.view', 'roles.create', 'posts.view'],
        ]);
        $this->actingAs(User::factory()->create(['role_id' => $managerRole->id]));

        $this->get('/vi/admin/roles/create')
            ->assertOk()
            ->assertSee('value="posts.view"', false)
            ->assertDontSee('value="settings.update"', false);
    }

    public function test_admin_can_create_role_via_form(): void
    {
        $this->actingAs($this->superadmin());

        $response = $this->post('/vi/admin/roles', [
            'name' => 'Product Manager',
            'permissions' => ['products.view', 'products.update', 'orders.view'],
        ]);

        $response->assertRedirect('/vi/admin/roles');

        $this->assertDatabaseHas('roles', ['name' => 'Product Manager']);

        $role = Role::where('name', 'Product Manager')->first();
        $this->assertEqualsCanonicalizing(
            ['products.view', 'products.update', 'orders.view'],
            $role->permissions,
        );
        // Stored on the pivot, not on a JSON column.
        $this->assertDatabaseCount('role_permission', 3);
    }

    public function test_admin_can_update_role(): void
    {
        $this->actingAs($this->superadmin());

        $role = Role::create([
            'name' => 'Staff',
            'permissions' => ['vouchers.view'],
        ]);

        $response = $this->put("/vi/admin/roles/{$role->id}", [
            'name' => 'Senior Staff',
            'permissions' => ['vouchers.view', 'orders.view'],
        ]);

        $response->assertRedirect('/vi/admin/roles');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Senior Staff']);
        $this->assertEqualsCanonicalizing(['vouchers.view', 'orders.view'], $role->fresh()->permissions);
    }

    public function test_unknown_permission_codes_are_rejected(): void
    {
        $this->actingAs($this->superadmin());

        $this->post('/vi/admin/roles', [
            'name' => 'Bogus',
            'permissions' => ['products.launch_missiles'],
        ])->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'Bogus']);
    }

    public function test_admin_can_delete_unused_role(): void
    {
        $this->actingAs($this->superadmin());

        $role = Role::create(['name' => 'Unused Role', 'permissions' => []]);

        $this->delete("/vi/admin/roles/{$role->id}")->assertRedirect('/vi/admin/roles');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_admin_cannot_delete_role_in_use(): void
    {
        $this->actingAs($this->superadmin());

        $role = Role::create(['name' => 'In Use Role', 'permissions' => []]);
        User::factory()->create(['role_id' => $role->id]);

        $response = $this->delete("/vi/admin/roles/{$role->id}");
        $response->assertRedirect('/vi/admin/roles');
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_user_without_roles_permission_cannot_access_roles(): void
    {
        $role = Role::create(['name' => 'Staff', 'permissions' => ['products.view']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));

        $this->get('/vi/admin/roles')->assertStatus(403);
    }

    /**
     * The point of activating `roles.*`: permission management no longer
     * requires a superadmin.
     */
    public function test_non_superadmin_with_roles_permission_can_manage_roles(): void
    {
        $managerRole = Role::create([
            'name' => 'Permission Manager',
            'permissions' => ['roles.view', 'roles.create', 'roles.update', 'roles.delete', 'posts.view', 'posts.update'],
        ]);
        $this->actingAs(User::factory()->create(['role_id' => $managerRole->id]));

        $this->get('/vi/admin/roles')->assertOk();

        $this->post('/vi/admin/roles', [
            'name' => 'Junior Editor',
            'permissions' => ['posts.view'],
        ])->assertRedirect('/vi/admin/roles');

        $this->assertSame(['posts.view'], Role::where('name', 'Junior Editor')->first()->permissions);
    }

    public function test_role_manager_cannot_grant_permissions_they_do_not_hold(): void
    {
        $managerRole = Role::create([
            'name' => 'Permission Manager',
            'permissions' => ['roles.view', 'roles.create', 'roles.update', 'posts.view'],
        ]);
        $this->actingAs(User::factory()->create(['role_id' => $managerRole->id]));

        $this->post('/vi/admin/roles', [
            'name' => 'Escalated',
            'permissions' => ['posts.view', 'users.create'],
        ])->assertStatus(403);

        $this->assertDatabaseMissing('roles', ['name' => 'Escalated']);
    }

    public function test_role_manager_cannot_escalate_their_own_role(): void
    {
        $managerRole = Role::create([
            'name' => 'Permission Manager',
            'permissions' => ['roles.view', 'roles.update', 'posts.view'],
        ]);
        $this->actingAs(User::factory()->create(['role_id' => $managerRole->id]));

        $this->put("/vi/admin/roles/{$managerRole->id}", [
            'name' => 'Permission Manager',
            'permissions' => ['roles.view', 'roles.update', 'posts.view', 'settings.update'],
        ])->assertStatus(403);

        $this->assertNotContains('settings.update', $managerRole->fresh()->permissions);
    }

    /**
     * Editing a role must not quietly strip permissions the editor cannot see,
     * otherwise a narrow admin becomes a demotion tool.
     */
    public function test_editing_a_role_preserves_permissions_beyond_the_editor_reach(): void
    {
        $managerRole = Role::create([
            'name' => 'Permission Manager',
            'permissions' => ['roles.view', 'roles.update', 'posts.view', 'posts.update'],
        ]);
        $target = Role::create([
            'name' => 'Mixed',
            'permissions' => ['posts.view', 'settings.update'],
        ]);
        $this->actingAs(User::factory()->create(['role_id' => $managerRole->id]));

        $this->put("/vi/admin/roles/{$target->id}", [
            'name' => 'Mixed',
            'permissions' => ['posts.view', 'posts.update'],
        ])->assertRedirect('/vi/admin/roles');

        $this->assertEqualsCanonicalizing(
            ['posts.view', 'posts.update', 'settings.update'],
            $target->fresh()->permissions,
        );
    }

    public function test_non_superadmin_cannot_see_or_manage_the_superadmin_role(): void
    {
        $role = Role::create([
            'name' => 'Regular Admin',
            'permissions' => ['users.view', 'roles.view', 'roles.update', 'roles.delete'],
        ]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));

        $this->get('/vi/admin/roles')
            ->assertOk()
            ->assertDontSee('Superadmin');

        $this->get("/vi/admin/roles/{$this->superadminRole->id}/edit")
            ->assertRedirect('/vi/admin/roles')
            ->assertSessionHas('error');

        $this->put("/vi/admin/roles/{$this->superadminRole->id}", [
            'name' => 'Superadmin Hack',
            'permissions' => [],
        ])->assertRedirect('/vi/admin/roles');

        $this->delete("/vi/admin/roles/{$this->superadminRole->id}")->assertRedirect('/vi/admin/roles');

        $this->assertDatabaseHas('roles', ['id' => $this->superadminRole->id, 'name' => 'Superadmin']);
        $this->assertTrue($this->superadminRole->fresh()->is_superadmin);
    }

    public function test_wildcard_on_an_ordinary_role_is_expanded_without_permission_management(): void
    {
        $role = Role::create(['name' => 'Broad', 'permissions' => ['*']]);

        $this->assertFalse($role->fresh()->is_superadmin);
        $this->assertContains('products.delete', $role->fresh()->permissions);
        $this->assertNotContains('roles.create', $role->fresh()->permissions);
    }
}
