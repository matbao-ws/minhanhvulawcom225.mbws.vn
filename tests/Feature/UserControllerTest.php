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
            'permissions' => ['*'],
        ]);

        $this->editorRole = Role::query()->create([
            'name' => 'Editor',
            'permissions' => ['products.*'],
        ]);

        Storage::fake('public');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/vi/admin/users');
        $response->assertRedirect('/login');
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
            'password' => 'secret123',
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
        $this->assertTrue(Hash::check('secret123', $user->password));
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
            'password' => 'password123',
            'role_id' => $superadminRole->id,
            'is_active' => 1,
        ]);
        $responseStore->assertStatus(403);
    }
}
