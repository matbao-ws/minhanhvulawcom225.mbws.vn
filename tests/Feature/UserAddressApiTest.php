<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAddressApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create([
            'name' => 'User One',
            'email' => 'user1@example.com',
        ]);

        $this->user2 = User::factory()->create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
        ]);
    }

    public function test_guest_cannot_access_addresses(): void
    {
        // Get list
        $this->getJson('/api/public/addresses')->assertStatus(401);

        // Store
        $this->postJson('/api/public/addresses', [
            'customer_name' => 'Test Name',
            'customer_phone' => '0988776655',
            'address' => '123 Test St',
        ])->assertStatus(401);
    }

    public function test_user_can_list_their_addresses(): void
    {
        Sanctum::actingAs($this->user1);

        // Empty list initially
        $response = $this->getJson('/api/public/addresses');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));

        // Create address
        $address = UserAddress::query()->create([
            'user_id' => $this->user1->id,
            'customer_name' => 'Home',
            'customer_phone' => '0912345678',
            'address' => '789 District 1, HCMC',
            'is_default' => true,
        ]);

        $response = $this->getJson('/api/public/addresses');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonFragment(['customer_name' => 'Home']);
    }

    public function test_user_can_create_address_and_resets_defaults(): void
    {
        Sanctum::actingAs($this->user1);

        // First address should automatically be default
        $response = $this->postJson('/api/public/addresses', [
            'customer_name' => 'Address 1',
            'customer_phone' => '0987654321',
            'address' => 'First Address Info',
            'is_default' => false, // even if passed false, since it's the first it becomes true
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.is_default'));
        $addr1Id = $response->json('data.id');

        // Second address without default
        $response2 = $this->postJson('/api/public/addresses', [
            'customer_name' => 'Address 2',
            'customer_phone' => '0911223344',
            'address' => 'Second Address Info',
            'is_default' => false,
        ]);

        $response2->assertStatus(200);
        $this->assertFalse($response2->json('data.is_default'));

        // Third address as default
        $response3 = $this->postJson('/api/public/addresses', [
            'customer_name' => 'Address 3',
            'customer_phone' => '0955667788',
            'address' => 'Third Address Info',
            'is_default' => true,
        ]);

        $response3->assertStatus(200);
        $this->assertTrue($response3->json('data.is_default'));

        // Check that Address 1 is no longer default
        $this->assertFalse(UserAddress::find($addr1Id)->is_default);
    }

    public function test_user_can_update_their_address(): void
    {
        Sanctum::actingAs($this->user1);

        $address = UserAddress::query()->create([
            'user_id' => $this->user1->id,
            'customer_name' => 'Old Name',
            'customer_phone' => '012345',
            'address' => 'Old Address',
            'is_default' => true,
        ]);

        $response = $this->putJson("/api/public/addresses/{$address->id}", [
            'customer_name' => 'New Name',
            'customer_phone' => '0988776655',
            'address' => 'New Address Detail',
            'is_default' => false, // default remains true since it was already true and is_default => false does not demote it if no other default is chosen
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['customer_name' => 'New Name']);
        $this->assertTrue($response->json('data.is_default'));
    }

    public function test_user_cannot_update_or_delete_other_users_address(): void
    {
        // User 2 owns this address
        $address = UserAddress::query()->create([
            'user_id' => $this->user2->id,
            'customer_name' => 'User 2 Home',
            'customer_phone' => '0987654321',
            'address' => 'User 2 Address',
            'is_default' => true,
        ]);

        // Log in as User 1
        Sanctum::actingAs($this->user1);

        // Try update
        $this->putJson("/api/public/addresses/{$address->id}", [
            'customer_name' => 'Hacker Name',
            'customer_phone' => '0988776655',
            'address' => 'Hacked Address',
        ])->assertStatus(403);

        // Try delete
        $this->deleteJson("/api/public/addresses/{$address->id}")->assertStatus(403);
    }

    public function test_user_can_delete_their_address_and_auto_assign_new_default(): void
    {
        Sanctum::actingAs($this->user1);

        $addr1 = UserAddress::query()->create([
            'user_id' => $this->user1->id,
            'customer_name' => 'Addr 1',
            'customer_phone' => '0000',
            'address' => 'Addr 1 Address',
            'is_default' => false,
        ]);

        $addr2 = UserAddress::query()->create([
            'user_id' => $this->user1->id,
            'customer_name' => 'Addr 2',
            'customer_phone' => '1111',
            'address' => 'Addr 2 Address',
            'is_default' => true,
        ]);

        // Delete default address (addr2)
        $response = $this->deleteJson("/api/public/addresses/{$addr2->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('user_addresses', ['id' => $addr2->id]);

        // Check if addr1 is now default
        $this->assertTrue($addr1->fresh()->is_default);
    }

    public function test_user_can_set_address_as_default_directly(): void
    {
        Sanctum::actingAs($this->user1);

        $addr1 = UserAddress::query()->create([
            'user_id' => $this->user1->id,
            'customer_name' => 'Addr 1',
            'customer_phone' => '0000',
            'address' => 'Addr 1 Address',
            'is_default' => true,
        ]);

        $addr2 = UserAddress::query()->create([
            'user_id' => $this->user1->id,
            'customer_name' => 'Addr 2',
            'customer_phone' => '1111',
            'address' => 'Addr 2 Address',
            'is_default' => false,
        ]);

        $response = $this->patchJson("/api/public/addresses/{$addr2->id}/set-default");
        $response->assertStatus(200);

        $this->assertTrue($addr2->fresh()->is_default);
        $this->assertFalse($addr1->fresh()->is_default);
    }
}
