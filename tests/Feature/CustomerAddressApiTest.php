<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\UserAddressController;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A customer's address book: only their own addresses, in a bounded number.
 */
class CustomerAddressApiTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = ['customer_name' => 'Người nhận', 'customer_phone' => '0900000000', 'address' => '1 Lê Lợi, HCM'];

    public function test_a_customer_cannot_read_change_or_delete_another_customers_address(): void
    {
        $owner = User::factory()->create(['role_id' => null]);
        $address = UserAddress::query()->create(['user_id' => $owner->id, ...self::PAYLOAD, 'is_default' => true]);

        Sanctum::actingAs(User::factory()->create(['role_id' => null]), ['customer']);

        $this->getJson('/api/public/addresses')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/public/addresses/{$address->id}", [...self::PAYLOAD, 'address' => 'Đã bị sửa'])->assertForbidden();
        $this->patchJson("/api/public/addresses/{$address->id}/set-default")->assertForbidden();
        $this->deleteJson("/api/public/addresses/{$address->id}")->assertForbidden();

        $this->assertSame('1 Lê Lợi, HCM', $address->fresh()->address);
    }

    public function test_addresses_do_not_expose_the_owner_id(): void
    {
        Sanctum::actingAs(User::factory()->create(['role_id' => null]), ['customer']);

        $this->postJson('/api/public/addresses', self::PAYLOAD)
            ->assertOk()
            ->assertJsonPath('data.address', '1 Lê Lợi, HCM')
            ->assertJsonMissingPath('data.user_id');
    }

    public function test_an_account_keeps_a_bounded_number_of_addresses(): void
    {
        $customer = User::factory()->create(['role_id' => null]);
        foreach (range(1, UserAddressController::MAX_ADDRESSES) as $i) {
            UserAddress::query()->create(['user_id' => $customer->id, ...self::PAYLOAD, 'is_default' => $i === 1]);
        }
        Sanctum::actingAs($customer, ['customer']);

        $this->postJson('/api/public/addresses', self::PAYLOAD)->assertStatus(422);
        $this->assertSame(UserAddressController::MAX_ADDRESSES, $customer->addresses()->count());
    }
}
