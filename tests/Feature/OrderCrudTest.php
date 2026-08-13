<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAddress;
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
        $response->assertRedirect('/vi/admin/login');
        $this->getJson('/vi/admin/orders/customer-suggestions?q=test')->assertUnauthorized();
    }

    public function test_users_without_admin_role_cannot_access_orders(): void
    {
        $customer = User::factory()->create([
            'role_id' => null,
        ]);

        $this->actingAs($customer);

        $response = $this->get('/vi/admin/orders');
        $response->assertStatus(403);
        $this->getJson('/vi/admin/orders/customer-suggestions?q=test')->assertForbidden();
    }

    public function test_manual_order_form_has_ajax_customer_autofill(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->get('/vi/admin/orders/create')
            ->assertOk()
            ->assertSee('placeholder="Nhập tên khách hàng..."', false)
            ->assertSee('placeholder="Nhập email..."', false)
            ->assertSee('placeholder="Nhập số điện thoại..."', false)
            ->assertSee('id="customer-suggestions"', false)
            ->assertSee('const lookupUrl =', false);
    }

    public function test_admin_can_find_registered_and_previous_order_customers_for_autofill(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $customer = User::factory()->create([
            'role_id' => null,
            'name' => 'Nguyễn Minh Anh',
            'email' => 'minhanh@example.com',
        ]);
        UserAddress::query()->create([
            'user_id' => $customer->id,
            'customer_name' => 'Nguyễn Minh Anh',
            'customer_phone' => '0909123456',
            'address' => '12 Nguyễn Huệ, Quận 1',
            'is_default' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/vi/admin/orders/customer-suggestions?q=090912')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Nguyễn Minh Anh')
            ->assertJsonPath('data.0.email', 'minhanh@example.com')
            ->assertJsonPath('data.0.phone', '0909123456')
            ->assertJsonPath('data.0.address', '12 Nguyễn Huệ, Quận 1')
            ->assertJsonPath('data.0.source', 'account');

        $this->actingAs($admin)
            ->getJson('/vi/admin/orders/customer-suggestions?q=098765')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Test Customer')
            ->assertJsonPath('data.0.email', 'customer@test.com')
            ->assertJsonPath('data.0.phone', '0987654321')
            ->assertJsonPath('data.0.address', '123 Test Street, Hanoi')
            ->assertJsonPath('data.0.source', 'order');
    }

    public function test_customer_autofill_requires_a_meaningful_search_term(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->getJson('/vi/admin/orders/customer-suggestions?q=a')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
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

    public function test_empty_order_state_is_rendered_outside_the_responsive_table(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);
        $this->order->delete();

        $this->actingAs($admin)
            ->get('/vi/admin/orders')
            ->assertOk()
            ->assertSee('id="orders-empty-state"', false)
            ->assertSee('admin-assets/images/icons/order-empty.png', false)
            ->assertDontSee('colspan="8"', false);
    }

    public function test_admin_can_view_order_details(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/orders/'.$this->order->id);
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
            'shipping_status' => 'delivered',
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

        // 4. Filter by shipping status = delivered
        $responseShippingStatus = $this->get('/vi/admin/orders?shipping_status=delivered');
        $responseShippingStatus->assertOk();
        $ordersShippingStatus = $responseShippingStatus->viewData('orders');
        $this->assertTrue($ordersShippingStatus->contains($completedOrder));
        $this->assertFalse($ordersShippingStatus->contains($this->order));
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->patch('/vi/admin/orders/'.$this->order->id.'/status', [
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        $response->assertRedirect('/vi/admin/orders/'.$this->order->id);
        $response->assertSessionHas('success');

        $this->order->refresh();
        $this->assertSame('processing', $this->order->status);
        $this->assertSame('paid', $this->order->payment_status);
    }
}
