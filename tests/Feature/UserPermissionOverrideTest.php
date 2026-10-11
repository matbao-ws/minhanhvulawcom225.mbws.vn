<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-account grants and revocations layered on top of the role.
 */
class UserPermissionOverrideTest extends TestCase
{
    use RefreshDatabase;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        (new PermissionSeeder)->run();

        foreach (['catalog', 'cms_page', 'multi_admin'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }

        $this->staffRole = Role::query()->create([
            'name' => 'Staff',
            'permissions' => ['posts.view', 'posts.update'],
        ]);
    }

    private function override(User $user, string $code, bool $granted): void
    {
        $user->permissionOverrides()->attach(
            Permission::query()->where('code', $code)->value('id'),
            ['granted' => $granted],
        );
        $user->forgetPermissionOverrideCache();
    }

    public function test_a_granted_override_adds_access_the_role_lacks(): void
    {
        $user = User::factory()->create(['role_id' => $this->staffRole->id]);
        $this->override($user, 'orders.view', true);

        $this->actingAs($user)->get('/vi/admin/orders')->assertOk();
    }

    public function test_a_revoked_override_removes_access_the_role_grants(): void
    {
        $user = User::factory()->create(['role_id' => $this->staffRole->id]);
        $this->override($user, 'posts.view', false);

        $this->actingAs($user)->get('/vi/admin/posts')->assertForbidden();
    }

    public function test_a_revocation_beats_the_role_but_never_the_superadmin(): void
    {
        $superadminRole = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $superadmin = User::factory()->create(['role_id' => $superadminRole->id]);
        $this->override($superadmin, 'posts.view', false);

        $this->actingAs($superadmin)->get('/vi/admin/posts')->assertOk();
    }

    public function test_effective_codes_merge_role_and_overrides(): void
    {
        $user = User::factory()->create(['role_id' => $this->staffRole->id]);
        $this->override($user, 'orders.view', true);
        $this->override($user, 'posts.update', false);

        $this->assertEqualsCanonicalizing(
            ['posts.view', 'orders.view'],
            $user->fresh()->effectivePermissionCodes(),
        );
    }

    public function test_the_override_matrix_renders_for_others_and_hides_for_yourself(): void
    {
        $manager = $this->actingAsUserManager(['orders.view']);
        $target = User::factory()->create(['role_id' => $this->staffRole->id]);

        $this->get('/vi/admin/users/'.$target->id.'/edit')
            ->assertOk()
            ->assertSee('permission_overrides[orders.view]', false)
            // Only permissions the manager holds are offered.
            ->assertDontSee('permission_overrides[settings.update]', false);

        $this->get('/vi/admin/users/'.$manager->id.'/edit')
            ->assertOk()
            ->assertDontSee('permission_overrides', false);
    }

    public function test_an_admin_can_grant_an_override_through_the_user_form(): void
    {
        $manager = $this->actingAsUserManager(['orders.view']);
        $target = User::factory()->create(['role_id' => $this->staffRole->id]);

        $this->put('/vi/admin/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $this->staffRole->id,
            'is_active' => 1,
            'permission_overrides' => ['orders.view' => 'grant'],
        ])->assertRedirect('/vi/admin/users');

        $this->assertTrue($target->fresh()->permissionOverrideFor('orders.view'));
        $this->assertSame($manager->id, auth()->id());
    }

    public function test_an_admin_cannot_grant_an_override_beyond_their_own_reach(): void
    {
        $this->actingAsUserManager([]);
        $target = User::factory()->create(['role_id' => $this->staffRole->id]);

        $this->put('/vi/admin/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $this->staffRole->id,
            'is_active' => 1,
            'permission_overrides' => ['settings.update' => 'grant'],
        ])->assertForbidden();

        $this->assertNull($target->fresh()->permissionOverrideFor('settings.update'));
    }

    public function test_an_admin_cannot_edit_their_own_overrides(): void
    {
        $manager = $this->actingAsUserManager(['orders.view']);

        $this->put('/vi/admin/users/'.$manager->id, [
            'name' => $manager->name,
            'email' => $manager->email,
            'role_id' => $manager->role_id,
            'is_active' => 1,
            'permission_overrides' => ['orders.view' => 'grant'],
        ])->assertForbidden();

        $this->assertDatabaseCount('user_permissions', 0);
    }

    /**
     * A narrow admin editing someone else must not silently wipe overrides on
     * permissions they cannot even see. (A revocation: a grant beyond the
     * editor's reach puts the whole account beyond it.)
     */
    public function test_overrides_outside_the_editor_reach_survive_a_save(): void
    {
        $target = User::factory()->create(['role_id' => $this->staffRole->id]);
        $this->override($target, 'settings.update', false);

        $this->actingAsUserManager(['orders.view']);

        $this->put('/vi/admin/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $this->staffRole->id,
            'is_active' => 1,
            'permission_overrides' => ['orders.view' => 'grant'],
        ])->assertRedirect('/vi/admin/users');

        $fresh = $target->fresh();
        $this->assertFalse($fresh->permissionOverrideFor('settings.update'));
        $this->assertTrue($fresh->permissionOverrideFor('orders.view'));
    }

    public function test_an_admin_cannot_create_an_account_on_a_role_beyond_their_reach(): void
    {
        $this->actingAsUserManager([]);
        $power = Role::query()->create(['name' => 'Power', 'permissions' => ['posts.view', 'settings.update']]);

        $this->post('/vi/admin/users', [
            'name' => 'Sock Puppet',
            'email' => 'sock@example.com',
            'role_id' => $power->id,
            'is_active' => 1,
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sock@example.com']);
    }

    public function test_an_admin_can_create_an_account_on_a_role_within_their_reach(): void
    {
        $this->actingAsUserManager([]);

        $this->post('/vi/admin/users', [
            'name' => 'New Staff',
            'email' => 'staff@example.com',
            'role_id' => $this->staffRole->id,
            'is_active' => 1,
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ])->assertRedirect('/vi/admin/users');

        $this->assertDatabaseHas('users', ['email' => 'staff@example.com', 'role_id' => $this->staffRole->id]);
    }

    public function test_an_admin_cannot_move_an_account_onto_a_role_beyond_their_reach(): void
    {
        $this->actingAsUserManager([]);
        $power = Role::query()->create(['name' => 'Power', 'permissions' => ['posts.view', 'settings.update']]);
        $target = User::factory()->create(['role_id' => $this->staffRole->id]);

        $this->put('/vi/admin/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $power->id,
            'is_active' => 1,
        ])->assertForbidden();

        $this->assertSame($this->staffRole->id, $target->fresh()->role_id);
    }

    public function test_the_role_picker_only_offers_roles_within_reach(): void
    {
        $this->actingAsUserManager([]);
        Role::query()->create(['name' => 'Power Role', 'permissions' => ['settings.update']]);

        $this->get('/vi/admin/users/create')
            ->assertOk()
            ->assertSee('Staff')
            ->assertDontSee('Power Role');
    }

    /**
     * The edit form sets passwords, so managing a more powerful account is
     * taking it over.
     */
    public function test_an_admin_cannot_take_over_an_account_holding_access_they_lack(): void
    {
        $power = Role::query()->create(['name' => 'Power', 'permissions' => ['posts.view', 'settings.update']]);
        $byRole = User::factory()->create(['role_id' => $power->id]);
        $byOverride = User::factory()->create(['role_id' => $this->staffRole->id]);
        $this->override($byOverride, 'settings.update', true);

        $this->actingAsUserManager([]);

        foreach ([$byRole, $byOverride] as $target) {
            $hash = $target->password;

            $this->get('/vi/admin/users/'.$target->id.'/edit')->assertForbidden();
            $this->put('/vi/admin/users/'.$target->id, [
                'name' => $target->name,
                'email' => $target->email,
                'role_id' => $target->role_id,
                'is_active' => 1,
                'password' => 'Str0ng!Passw0rd',
                'password_confirmation' => 'Str0ng!Passw0rd',
            ])->assertForbidden();
            $this->patch('/vi/admin/users/'.$target->id.'/toggle-status')->assertForbidden();

            $fresh = $target->fresh();
            $this->assertSame($hash, $fresh->password);
            $this->assertTrue($fresh->is_active);
        }
    }

    /** Keeping your own role grants nothing, even when an override trims it. */
    public function test_an_admin_whose_override_trims_their_role_can_still_save_their_profile(): void
    {
        $manager = $this->actingAsUserManager([]);
        $this->override($manager, 'posts.update', false);

        $this->put('/vi/admin/users/'.$manager->id, [
            'name' => 'Renamed',
            'email' => $manager->email,
            'role_id' => $manager->role_id,
            'is_active' => 1,
        ])->assertRedirect('/vi/admin/users');

        $this->assertSame('Renamed', $manager->fresh()->name);
    }

    /**
     * The manager holds the Staff role's permissions too: managing an account
     * that holds access the manager lacks is refused outright.
     */
    private function actingAsUserManager(array $extraPermissions): User
    {
        $role = Role::query()->create([
            'name' => 'User Manager',
            'permissions' => array_merge(['users.view', 'users.create', 'users.update', 'posts.view', 'posts.update'], $extraPermissions),
        ]);
        $manager = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($manager);

        return $manager;
    }
}
