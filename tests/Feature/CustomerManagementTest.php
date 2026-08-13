<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CustomerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->create([
            'name' => 'Sales manager',
            'permissions' => ['view_customers'],
        ]);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_authorized_admin_can_view_customer_directory_and_profile(): void
    {
        $registeredCustomer = User::factory()->create([
            'name' => 'Registered Shopper',
            'email' => 'shopper@example.com',
            'role_id' => null,
        ]);
        $this->createOrder([
            'order_number' => 'CUS-001',
            'user_id' => $registeredCustomer->id,
            'customer_name' => 'Old checkout name',
            'customer_email' => 'SHOPPER@example.com',
            'status' => 'completed',
            'grand_total' => 250000,
        ]);
        $this->createOrder([
            'order_number' => 'CUS-002',
            'user_id' => $registeredCustomer->id,
            'customer_name' => 'New checkout name',
            'customer_email' => 'shopper@example.com',
            'status' => 'processing',
            'grand_total' => 100000,
        ]);
        $this->createOrder([
            'order_number' => 'GUEST-001',
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'guest@example.com',
            'customer_phone' => '0900000002',
            'status' => 'completed',
            'grand_total' => 50000,
        ]);

        $response = $this->actingAs($this->admin)->get('/vi/admin/customers?q=shopper');

        $response->assertOk()->assertViewIs('admin.customers.index')->assertSee('Old checkout name');
        $customer = $response->viewData('customers')->items()[0];
        $this->assertSame(2, (int) $customer->total_orders);
        $this->assertSame(1, (int) $customer->completed_orders);
        $this->assertSame(250000.0, (float) $customer->total_spent);
        $this->assertSame($registeredCustomer->id, $customer->registered_user_id);

        $this->actingAs($this->admin)
            ->get('/vi/admin/customers/profile?email=shopper@example.com')
            ->assertOk()
            ->assertViewIs('admin.customers.show')
            ->assertSee('Registered Shopper')
            ->assertSee('Thông tin liên hệ')
            ->assertSee('Tỷ lệ hoàn tất')
            ->assertSee('CUS-001')
            ->assertSee('CUS-002');
    }

    public function test_admin_without_customer_permission_cannot_access_customer_directory(): void
    {
        $role = Role::query()->create(['name' => 'Order staff', 'permissions' => ['manage_orders']]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get('/vi/admin/customers')->assertForbidden();
    }

    public function test_customer_demo_seeder_is_idempotent_and_does_not_create_admins(): void
    {
        $this->seed(CustomerDemoSeeder::class);
        $this->seed(CustomerDemoSeeder::class);

        $this->assertSame(8, User::query()->whereNull('role_id')->where('email', 'like', '%.customer@example.test')->count());
        $this->assertDatabaseCount('user_addresses', 8);
        $this->assertSame(8, Order::query()->where('order_number', 'like', 'CUSTOMER-DEMO-%')->count());
        $this->assertSame(1, User::query()->whereNotNull('role_id')->count());
        $this->assertDatabaseHas('user_addresses', [
            'customer_name' => 'Nguyễn Minh Anh',
            'customer_phone' => '0909123456',
            'is_default' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createOrder(array $overrides): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => 'CUS-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '0900000001',
            'shipping_address' => '123 Test Street',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'completed',
            'subtotal' => 100000,
            'grand_total' => 100000,
        ], $overrides));
    }
}
