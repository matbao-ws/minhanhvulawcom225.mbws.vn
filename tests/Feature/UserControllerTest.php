<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $editorRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable catalog feature if required (some layouts need it)
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        FeatureSetting::query()->create([
            'feature_code' => 'multi_admin',
            'is_enabled' => true,
        ]);

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['users.view', 'users.create', 'users.update', 'users.delete'],
        ]);

        $this->editorRole = Role::query()->create([
            'name' => 'Editor',
            'permissions' => ['products.*'],
        ]);

        Storage::fake('public');
    }

    public function test_users_index_lists_staff_only_and_never_customers(): void
    {
        // Customers are users with no role. They belong on the customers screen; the
        // visibility filter used to exclude them only as a side effect of joining
        // `roles`, which meant a superadmin — who skips that filter — saw every
        // shopper listed among the staff accounts.
        $staff = User::factory()->create([
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'role_id' => $this->editorRole->id,
        ]);
        $customer = User::factory()->create([
            'name' => 'Shopper Person',
            'email' => 'shopper@example.com',
            'role_id' => null,
        ]);

        $superadminRole = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
            'is_system' => true,
            'is_superadmin' => true,
        ]);
        $superadmin = User::factory()->create(['role_id' => $superadminRole->id]);

        $response = $this->actingAs($superadmin)->get('/vi/admin/users');

        $response->assertOk();

        // Asserted on the paginator, not the page: the admin header also renders a
        // notification feed, so a whole-page assertion would be testing that feed
        // rather than this listing.
        $listed = $response->viewData('users')->pluck('email')->all();
        $this->assertContains($staff->email, $listed);
        $this->assertNotContains($customer->email, $listed);
    }

    public function test_header_notifications_do_not_advertise_customers_as_staff(): void
    {
        // The feed is gated on users.view, titled "Thành viên mới" and links to the
        // staff edit form, so a customer appearing there points the admin at a form
        // that cannot manage them.
        User::factory()->create(['name' => 'Shopper Person', 'email' => 'shopper@example.com', 'role_id' => null]);
        $staff = User::factory()->create(['email' => 'staff@example.com', 'role_id' => $this->editorRole->id]);
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $response = $this->actingAs($admin)->get('/vi/admin/users');

        // headerNotifications is bound to admin.layouts.header, not the page view, so
        // the assertion targets the markup that feed renders.
        $response->assertOk();
        $response->assertDontSee('Thành viên mới: Shopper Person', false);
        $response->assertSee('Thành viên mới: '.$staff->name, false);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/vi/admin/users');
        $response->assertRedirect('/vi/admin/login');
    }

    public function test_admin_can_access_users_index(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/users');

        $response->assertOk();
        $response->assertViewIs('admin.users.index');
        $response->assertViewHas('users');
    }

    public function test_admin_can_filter_users(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $editor = User::query()->create([
            'name' => 'John Editor',
            'email' => 'john@editor.com',
            'password' => bcrypt('password'),
            'role_id' => $this->editorRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        // Filter by keyword
        $responseKeyword = $this->get('/vi/admin/users?q=John');
        $responseKeyword->assertOk();
        $this->assertCount(1, $responseKeyword->viewData('users'));

        // Filter by role
        $responseRole = $this->get('/vi/admin/users?role_id=' . $this->editorRole->id);
        $responseRole->assertOk();
        $this->assertCount(1, $responseRole->viewData('users'));
    }

    public function test_admin_can_create_user_with_avatar(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $avatarFile = UploadedFile::fake()->image('avatar.png');

        $response = $this->post('/vi/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Secret123!abc',
            'role_id' => $this->editorRole->id,
            'is_active' => '1',
            'avatar_file' => $avatarFile,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role_id' => $this->editorRole->id,
            'is_active' => true,
        ]);

        $user = User::query()->where('email', 'newuser@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Secret123!abc', $user->password));
        $this->assertNotNull($user->avatar_url);
        $this->assertStringContainsString('avatars/', $user->avatar_url);
    }

    public function test_admin_can_update_user_details(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $userToEdit = User::query()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => bcrypt('oldpassword'),
            'role_id' => $this->editorRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        // Update details without updating password
        $response = $this->put('/vi/admin/users/' . $userToEdit->id, [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'role_id' => $this->adminRole->id,
            'is_active' => '1',
            'password' => '', // Empty password should be ignored
        ]);

        $response->assertRedirect();
        
        $userToEdit->refresh();
        $this->assertEquals('New Name', $userToEdit->name);
        $this->assertEquals('new@example.com', $userToEdit->email);
        $this->assertEquals($this->adminRole->id, $userToEdit->role_id);
        $this->assertTrue(Hash::check('oldpassword', $userToEdit->password)); // Password unchanged
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $userToDelete = User::factory()->create([
            'role_id' => $this->editorRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->delete('/vi/admin/users/' . $userToDelete->id);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', [
            'id' => $userToDelete->id,
        ]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->delete('/vi/admin/users/' . $admin->id);

        $response->assertRedirect();
        $response->assertSessionHas('error'); // Error message instead of success

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }

    public function test_admin_show_redirects_to_edit(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $user = User::factory()->create([
            'role_id' => $this->editorRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/users/' . $user->id);

        $response->assertRedirect('/vi/admin/users/' . $user->id . '/edit');
    }

    public function test_non_superadmin_cannot_see_superadmin_role_or_assign_it(): void
    {
        $superadminRole = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        // 1. Fetching users/create page should not include Superadmin role
        $responseCreate = $this->get('/vi/admin/users/create');
        $responseCreate->assertOk();
        $rolesInCreate = $responseCreate->viewData('roles');
        $this->assertFalse($rolesInCreate->contains('name', 'Superadmin'));

        // 2. Fetching users/edit page should not include Superadmin role
        $someUser = User::factory()->create([
            'role_id' => $this->editorRole->id,
        ]);
        $responseEdit = $this->get("/vi/admin/users/{$someUser->id}/edit");
        $responseEdit->assertOk();
        $rolesInEdit = $responseEdit->viewData('roles');
        $this->assertFalse($rolesInEdit->contains('name', 'Superadmin'));

        // 3. Attempting to assign Superadmin role to a user should abort with 403
        $responseStore = $this->post('/vi/admin/users', [
            'name' => 'Should Fail User',
            'email' => 'shouldfail@example.com',
            'password' => 'Password123!x',
            'role_id' => $superadminRole->id,
            'is_active' => 1,
        ]);
        $responseStore->assertStatus(403);
    }
}
