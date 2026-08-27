<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\AdminActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['*'],
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
    }

    public function test_guests_cannot_access_dashboard(): void
    {
        $response = $this->get('/vi/admin/dashboard');

        // Guests should be redirected to the login page
        $response->assertRedirect('/vi/admin/login');
    }

    public function test_guests_keep_locale_when_redirected_from_admin_root(): void
    {
        $this->get('/vi/admin')->assertRedirect('/vi/admin/login');
    }

    public function test_admin_can_access_dashboard_with_statistics(): void
    {
        // Create some orders for statistics
        Order::query()->create([
            'order_number' => 'ORD-001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '123 Test St',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000,
            'grand_total' => 100000,
        ]);

        Order::query()->create([
            'order_number' => 'ORD-002',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '0912345679',
            'shipping_address' => '456 Test St',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'completed',
            'subtotal' => 250000,
            'grand_total' => 250000,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/vi/admin/dashboard');

        $response->assertOk();
        $response->assertSee('admin-assets/js/form-guidance.js', false);
        $response->assertViewIs('admin.dashboard.index');
        $response->assertViewHasAll([
            'enabledFeatureCount',
            'metrics',
            'chart',
            'statusChart',
            'recentOrders',
        ]);

        // Check if metrics match calculations
        $metrics = $response->viewData('metrics');
        $this->assertEquals(2, $metrics['total_orders']);
        $this->assertEquals(250000, $metrics['total_revenue']);
        $this->assertEquals(1, $metrics['completed_orders']);
        $this->assertEquals(0, $metrics['processing_orders']);

        // Check recent orders
        $recentOrders = $response->viewData('recentOrders');
        $this->assertCount(2, $recentOrders);
    }

    public function test_admin_without_order_permission_can_access_a_safe_dashboard(): void
    {
        $role = Role::query()->create([
            'name' => 'Content editor',
            'permissions' => ['posts.view', 'posts.create', 'posts.update', 'posts.delete'],
        ]);
        $editor = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($editor)
            ->get('/vi/admin')
            ->assertOk()
            ->assertSee(__('admin.dashboard_page.limited_title'))
            ->assertDontSee(__('admin.dashboard_page.monthly_revenue'));
    }

    public function test_dashboard_groups_registered_customers_and_uses_actual_admin_activity(): void
    {
        $customer = User::factory()->create([
            'name' => 'Registered Customer',
            'email' => 'customer@example.com',
            'role_id' => null,
        ]);
        foreach ([100000, 250000] as $index => $amount) {
            Order::query()->create([
                'user_id' => $customer->id,
                'order_number' => 'VIP-'.($index + 1),
                'customer_name' => 'Checkout Name '.($index + 1),
                'customer_email' => $customer->email,
                'customer_phone' => '0912345678',
                'shipping_address' => '123 Test St',
                'payment_method' => 'cod',
                'payment_status' => 'paid',
                'status' => 'completed',
                'subtotal' => $amount,
                'grand_total' => $amount,
            ]);
        }
        AdminActivityLog::query()->create([
            'user_id' => $this->adminUser->id,
            'action' => 'updated',
            'description' => 'Cập nhật cấu hình',
            'created_at' => now(),
        ]);
        AdminActivityLog::query()->create([
            'user_id' => $this->adminUser->id,
            'action' => 'created',
            'description' => 'Tạo đơn hàng',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->get('/vi/admin/dashboard')->assertOk();

        $topCustomer = $response->viewData('topCustomers')->first();
        $this->assertSame($customer->id, (int) $topCustomer->user_id);
        $this->assertSame(2, (int) $topCustomer->total_orders);
        $this->assertSame(350000.0, (float) $topCustomer->total_spent);
        $activityChart = $response->viewData('activityChart');
        $this->assertSame(2, $activityChart['total']);
        $this->assertSame(2, $activityChart['data'][6]);
    }

    public function test_admin_can_access_notifications_list_with_pagination(): void
    {
        // Seed more than 15 orders to trigger pagination
        for ($i = 1; $i <= 20; $i++) {
            Order::query()->create([
                'order_number' => "ORD-PAG-{$i}",
                'customer_name' => "Customer {$i}",
                'customer_email' => "customer{$i}@example.com",
                'customer_phone' => '0912345678',
                'shipping_address' => '123 Test St',
                'payment_method' => 'cod',
                'payment_status' => 'pending',
                'status' => 'pending',
                'subtotal' => 100000,
                'grand_total' => 100000,
            ]);
        }

        $response = $this->actingAs($this->adminUser)->get('/vi/admin/notifications');

        $response->assertOk();
        $response->assertViewIs('admin.notifications.index');
        $response->assertViewHas('notifications');
        
        $notifications = $response->viewData('notifications');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $notifications);
        $this->assertCount(15, $notifications->items());
        $this->assertEquals(21, $notifications->total());
        
        // Fetch page 2
        $responsePage2 = $this->actingAs($this->adminUser)->get('/vi/admin/notifications?page=2');
        $responsePage2->assertOk();
        $notificationsPage2 = $responsePage2->viewData('notifications');
        $this->assertCount(6, $notificationsPage2->items());
    }

    public function test_admin_can_filter_notifications_by_type(): void
    {
        // Create an order
        Order::query()->create([
            'order_number' => 'ORD-999',
            'customer_name' => 'Searchable Order User',
            'customer_email' => 'searchable@example.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '123 Test St',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000,
            'grand_total' => 100000,
        ]);

        // Create a user
        User::factory()->create([
            'name' => 'New User Notification',
            'email' => 'newuser@example.com',
        ]);

        // Filter only orders
        $response = $this->actingAs($this->adminUser)->get('/vi/admin/notifications?type=orders');
        $response->assertOk();
        $notifications = $response->viewData('notifications');
        
        $this->assertTrue($notifications->contains(function($notif) {
            return str_contains($notif->title, 'ORD-999');
        }));
        
        $this->assertFalse($notifications->contains(function($notif) {
            return str_contains($notif->title, 'Thành viên mới');
        }));

        // Filter only users
        $response = $this->actingAs($this->adminUser)->get('/vi/admin/notifications?type=users');
        $response->assertOk();
        $notifications = $response->viewData('notifications');
        
        $this->assertTrue($notifications->contains(function($notif) {
            return str_contains($notif->title, 'Thành viên mới');
        }));
        
        $this->assertFalse($notifications->contains(function($notif) {
            return str_contains($notif->title, 'ORD-999');
        }));
    }

    public function test_admin_can_search_notifications_by_query(): void
    {
        // Create an order matching query
        Order::query()->create([
            'order_number' => 'SPECIAL-ORD',
            'customer_name' => 'Unique Customer Name',
            'customer_email' => 'unique@example.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '123 Test St',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000,
            'grand_total' => 100000,
        ]);

        // Create another order not matching query
        Order::query()->create([
            'order_number' => 'OTHER-ORD',
            'customer_name' => 'Regular Person',
            'customer_email' => 'regular@example.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '123 Test St',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000,
            'grand_total' => 100000,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/vi/admin/notifications?q=Unique');
        $response->assertOk();
        $notifications = $response->viewData('notifications');

        $this->assertTrue($notifications->contains(function($notif) {
            return str_contains($notif->message, 'Unique Customer Name');
        }));

        $this->assertFalse($notifications->contains(function($notif) {
            return str_contains($notif->message, 'Regular Person');
        }));
    }
}
