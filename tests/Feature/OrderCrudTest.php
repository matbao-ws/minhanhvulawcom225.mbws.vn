<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCrudTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable catalog feature
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['*'],
        ]);

        // Create a test order
        $this->order = Order::query()->create([
            'order_number' => 'ORD-TEST-001',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@test.com',
            'customer_phone' => '0987654321',
            'shipping_address' => '123 Test Street, Hanoi',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 200000.00,
            'discount' => 20000.00,
            'grand_total' => 180000.00,
            'notes' => 'Please deliver in afternoon.',
        ]);

        OrderItem::query()->create([
            'order_id' => $this->order->id,
            'product_name' => 'Test Product',
            'sku' => 'TEST-SKU',
            'price' => 200000.00,
            'quantity' => 1,
            'total' => 200000.00,
        ]);
    }

    public function test_guests_cannot_access_orders(): void
    {
        $response = $this->get('/vi/admin/orders');
        $response->assertRedirect('/login');
    }

    public function test_users_without_admin_role_cannot_access_orders(): void
    {
        $customer = User::factory()->create([
            'role_id' => null,
        ]);

        $this->actingAs($customer);

        $response = $this->get('/vi/admin/orders');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_orders_listing(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/orders');
        $response->assertOk();
        $response->assertViewIs('admin.orders.index');
        $response->assertViewHas('orders');

        $orders = $response->viewData('orders');
        $this->assertTrue($orders->contains($this->order));
    }

    public function test_admin_can_view_order_details(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/orders/' . $this->order->id);
        $response->assertOk();
        $response->assertViewIs('admin.orders.show');
        $response->assertViewHas('order');
        
        $response->assertSee('ORD-TEST-001');
        $response->assertSee('Test Customer');
        $response->assertSee('0987654321');
        $response->assertSee('180.000 ₫');
        $response->assertSee('Test Product');
    }

    public function test_admin_can_filter_and_search_orders(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        // Create another completed and paid order
        $completedOrder = Order::query()->create([
            'order_number' => 'ORD-COMPLETED-002',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@doe.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '456 Done Street, HCMC',
            'payment_method' => 'online',
            'payment_status' => 'paid',
            'status' => 'completed',
            'subtotal' => 300000.00,
            'discount' => 0,
            'grand_total' => 300000.00,
        ]);

        // 1. Search by customer name
        $responseSearch = $this->get('/vi/admin/orders?q=John Doe');
        $responseSearch->assertOk();
        $ordersSearch = $responseSearch->viewData('orders');
        $this->assertTrue($ordersSearch->contains($completedOrder));
        $this->assertFalse($ordersSearch->contains($this->order));

        // 2. Filter by order status = completed
        $responseFilterStatus = $this->get('/vi/admin/orders?status=completed');
        $responseFilterStatus->assertOk();
        $ordersFilterStatus = $responseFilterStatus->viewData('orders');
        $this->assertTrue($ordersFilterStatus->contains($completedOrder));
        $this->assertFalse($ordersFilterStatus->contains($this->order));

        // 3. Filter by payment status = paid
        $responseFilterPayment = $this->get('/vi/admin/orders?payment_status=paid');
        $responseFilterPayment->assertOk();
        $ordersFilterPayment = $responseFilterPayment->viewData('orders');
        $this->assertTrue($ordersFilterPayment->contains($completedOrder));
        $this->assertFalse($ordersFilterPayment->contains($this->order));
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->patch('/vi/admin/orders/' . $this->order->id . '/status', [
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        $response->assertRedirect('/vi/admin/orders/' . $this->order->id);
        $response->assertSessionHas('success');

        $this->order->refresh();
        $this->assertSame('processing', $this->order->status);
        $this->assertSame('paid', $this->order->payment_status);
    }
}
