<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['manage_settings'],
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->customer = User::factory()->create([
            'role_id' => null,
            'email' => 'customer@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_login_via_web(): void
    {
        $response = $this->postJson('/vi/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_customer_cannot_login_via_web_admin_login(): void
    {
        $response = $this->postJson('/vi/admin/login', [
            'email' => 'customer@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $this->assertGuest();
    }

    public function test_admin_can_login_via_api(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data' => ['access_token']]);
    }

    public function test_customer_cannot_login_via_api_admin_login(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'email' => 'customer@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }
}
