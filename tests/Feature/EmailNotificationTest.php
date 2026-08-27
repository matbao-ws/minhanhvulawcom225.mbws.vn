<?php

namespace Tests\Feature;

use App\Mail\OrderStatusMail;
use App\Jobs\SendOrderStatusEmail;
use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
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
            'order_number' => 'ORD-TEST-EMAIL',
            'customer_name' => 'John Mailer',
            'customer_email' => 'john.mailer@example.com',
            'customer_phone' => '0987654321',
            'shipping_address' => '123 Email St, TechCity',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000.00,
            'discount' => 0.00,
            'grand_total' => 100000.00,
        ]);

        OrderItem::query()->create([
            'order_id' => $this->order->id,
            'product_name' => 'Email Product Test',
            'sku' => 'EMAIL-TEST-SKU',
            'price' => 100000.00,
            'quantity' => 1,
            'total' => 100000.00,
        ]);
    }

    public function test_order_email_uses_the_configured_website_name_and_logo(): void
    {
        ProjectSetting::query()->create([
            'setting_key' => 'shop_name',
            'setting_value' => 'Cửa hàng email',
        ]);
        ProjectSetting::query()->create([
            'setting_key' => 'logo_url',
            'setting_value' => 'https://cdn.example.test/settings/email-logo.png',
        ]);

        // emails.layout is shared by every mailable, so asserting it here covers the
        // branding for all of them.
        $mail = new OrderStatusMail($this->order);
        $rendered = $mail->render();

        $this->assertStringContainsString('Cửa hàng email', $rendered);
        $this->assertStringContainsString('https://cdn.example.test/settings/email-logo.png', $rendered);
    }

    public function test_updating_order_status_triggers_order_status_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->patch("/vi/admin/orders/{$this->order->id}/status", [
            'status' => 'processing',
            'payment_status' => 'pending',
        ]);

        $response->assertRedirect("/vi/admin/orders/{$this->order->id}");

        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            return $mail->hasTo($this->order->customer_email) && 
                   $mail->order->id === $this->order->id &&
                   $mail->order->status === 'processing';
        });
    }

    public function test_updating_order_status_queues_the_email_for_a_worker(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)->patch("/vi/admin/orders/{$this->order->id}/status", [
            'status' => 'processing',
            'payment_status' => 'pending',
        ])->assertRedirect();

        Queue::assertPushed(SendOrderStatusEmail::class, function (SendOrderStatusEmail $job): bool {
            return $job->orderId === $this->order->id
                && $job->recipient === $this->order->customer_email;
        });
    }

    public function test_updating_only_payment_status_does_not_trigger_status_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->patch("/vi/admin/orders/{$this->order->id}/status", [
            'status' => 'pending', // No change in status
            'payment_status' => 'paid', // Only payment status changed
        ]);

        $response->assertRedirect("/vi/admin/orders/{$this->order->id}");

        Mail::assertNotSent(OrderStatusMail::class);
    }
}
