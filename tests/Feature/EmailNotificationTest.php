<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Mail\OrderStatusMail;
use App\Models\FeatureSetting;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Invoice $invoice;
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

        // Create a test invoice
        $this->invoice = Invoice::query()->create([
            'invoice_number' => 'INV-TEST-EMAIL',
            'package_name' => 'Test Email Package',
            'amount' => 50000.00,
            'status' => 'pending',
            'billing_date' => '2026-06-25',
            'due_date' => '2026-07-25',
            'payment_method' => null,
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

    public function test_admin_can_manually_send_invoice_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->post("/vi/admin/invoices/{$this->invoice->id}/send-email");
        $response->assertRedirect("/vi/admin/invoices/{$this->invoice->id}");
        $response->assertSessionHas('success');

        Mail::assertSent(InvoiceMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email) && $mail->invoice->id === $this->invoice->id;
        });
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
