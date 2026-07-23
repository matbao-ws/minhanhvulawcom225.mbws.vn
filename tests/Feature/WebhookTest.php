<?php

namespace Tests\Feature;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private string $webhookToken = 'super-secret-webhook-token-xyz';

    protected function setUp(): void
    {
        parent::setUp();

        // Seed GHTK settings in shipping_partners table
        ShippingPartner::query()
            ->where('partner_code', 'DTGH000012')
            ->first()
            ->update([
                'status' => 'active',
                'settings' => [
                    'api_token' => 'mock',
                    'api_url' => 'https://services.ghtk.vn',
                    'realtime_tracking_enabled' => true,
                    'webhook_token' => $this->webhookToken,
                ],
            ]);

        // Create a test order
        $this->order = Order::query()->create([
            'order_number' => 'ORD-WEBHOOK-001',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane.doe@example.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '123 Nguyen Hue, District 1, HCMC',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 300000.00,
            'discount' => 0.00,
            'grand_total' => 300000.00,
            'notes' => 'Deliver during working hours',
        ]);

        OrderItem::query()->create([
            'order_id' => $this->order->id,
            'product_name' => 'Test Product',
            'sku' => 'PROD-SKU',
            'price' => 300000.00,
            'quantity' => 1,
            'total' => 300000.00,
        ]);
    }

    public function test_ghtk_webhook_fails_with_invalid_token(): void
    {
        $response = $this->withHeader('X-GHTK-Token', 'wrong-token')->postJson('/api/webhooks/ghtk', [
            'partner_id' => $this->order->order_number,
            'label_id' => 'GHTK.123456',
            'status_id' => 3,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized.',
        ]);
    }

    public function test_ghtk_webhook_is_rejected_when_realtime_tracking_is_disabled(): void
    {
        $partner = ShippingPartner::query()
            ->where('partner_code', 'DTGH000012')
            ->firstOrFail();
        $settings = $partner->settings;
        $settings['realtime_tracking_enabled'] = false;
        $partner->update(['settings' => $settings]);

        $this->withHeader('X-GHTK-Token', $this->webhookToken)->postJson('/api/webhooks/ghtk', [
            'partner_id' => $this->order->order_number,
            'status_id' => 3,
        ])->assertUnauthorized();

        $this->assertSame('pending', $this->order->fresh()->status);
    }

    public function test_ghtk_webhook_fails_with_missing_partner_id(): void
    {
        $response = $this->withHeader('X-GHTK-Token', $this->webhookToken)->postJson('/api/webhooks/ghtk', [
            'label_id' => 'GHTK.123456',
            'status_id' => 3,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['partner_id']);
    }

    public function test_ghtk_webhook_fails_with_non_existing_order(): void
    {
        $response = $this->withHeader('X-GHTK-Token', $this->webhookToken)->postJson('/api/webhooks/ghtk', [
            'partner_id' => 'ORD-NON-EXISTENT',
            'label_id' => 'GHTK.123456',
            'status_id' => 3,
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Order not found.',
        ]);
    }

    public function test_ghtk_webhook_updates_status_to_processing(): void
    {
        Mail::fake();

        $response = $this->withHeader('X-GHTK-Token', $this->webhookToken)->postJson('/api/webhooks/ghtk', [
            'partner_id' => $this->order->order_number,
            'label_id' => 'GHTK.ABC123456',
            'status_id' => 3, // Đã lấy hàng
            'fee' => 28000,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'order_status' => 'processing',
            'payment_status' => 'pending',
            'shipping_status' => 'picked_up',
        ]);

        $this->order->refresh();
        $this->assertEquals('processing', $this->order->status);
        $this->assertEquals('GHTK.ABC123456', $this->order->tracking_number);
        $this->assertEquals(28000.00, $this->order->carrier_shipping_fee);
        $this->assertEquals(0.00, $this->order->shipping_fee);
        $this->assertEquals('ghtk', $this->order->shipping_carrier);
        $this->assertSame('picked_up', $this->order->shipping_status);
        $this->assertNotNull($this->order->shipping_status_updated_at);

        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            return $mail->hasTo($this->order->customer_email);
        });
    }

    public function test_ghtk_webhook_updates_status_to_completed_and_sets_payment_paid(): void
    {
        Mail::fake();

        // Pretend the order was processing
        $this->order->update(['status' => 'processing']);

        $response = $this->withHeader('X-GHTK-Token', $this->webhookToken)->postJson('/api/webhooks/ghtk', [
            'partner_id' => $this->order->order_number,
            'label_id' => 'GHTK.ABC123456',
            'status_id' => 5, // Đã giao hàng / Chưa đối soát
            'fee' => 32000,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'shipping_status' => 'delivered',
        ]);

        $this->order->refresh();
        $this->assertEquals('completed', $this->order->status);
        $this->assertEquals('paid', $this->order->payment_status);
        $this->assertEquals(32000.00, $this->order->carrier_shipping_fee);
        $this->assertSame('delivered', $this->order->shipping_status);

        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            return $mail->hasTo($this->order->customer_email);
        });
    }

    public function test_ghtk_webhook_updates_status_to_cancelled(): void
    {
        Mail::fake();

        $response = $this->withHeader('X-GHTK-Token', $this->webhookToken)->postJson('/api/webhooks/ghtk', [
            'partner_id' => $this->order->order_number,
            'label_id' => 'GHTK.ABC123456',
            'status_id' => -1, // Hủy đơn hàng
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'order_status' => 'cancelled',
            'shipping_status' => 'cancelled',
        ]);

        $this->order->refresh();
        $this->assertEquals('cancelled', $this->order->status);
        $this->assertSame('cancelled', $this->order->shipping_status);

        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            return $mail->hasTo($this->order->customer_email);
        });
    }

    public function test_ghtk_webhook_records_failed_pickup_without_cancelling_the_order(): void
    {
        Mail::fake();

        $response = $this->withHeader('X-GHTK-Token', $this->webhookToken)->postJson('/api/webhooks/ghtk', [
            'partner_id' => $this->order->order_number,
            'label_id' => 'GHTK.ABC123456',
            'status_id' => 7, // Không lấy được hàng
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'order_status' => 'pending',
            'shipping_status' => 'pickup_failed',
        ]);

        $this->order->refresh();
        $this->assertEquals('pending', $this->order->status);
        $this->assertSame('pickup_failed', $this->order->shipping_status);

        Mail::assertNothingSent();
    }

    public function test_ghtk_webhook_ignores_a_stale_status_that_would_reopen_an_order(): void
    {
        $this->order->update(['status' => 'processing']);

        $this->withHeader('X-GHTK-Token', $this->webhookToken)->postJson('/api/webhooks/ghtk', [
            'partner_id' => $this->order->order_number,
            'label_id' => 'GHTK.ABC123456',
            'status_id' => 1,
        ])->assertOk()->assertJson(['order_status' => 'processing']);

        $this->assertSame('processing', $this->order->fresh()->status);
    }

    public function test_ghtk_webhook_does_not_replace_a_terminal_shipping_status_with_a_stale_update(): void
    {
        $this->order->update([
            'status' => 'completed',
            'shipping_status' => 'delivered',
        ]);

        $this->withHeader('X-GHTK-Token', $this->webhookToken)->postJson('/api/webhooks/ghtk', [
            'partner_id' => $this->order->order_number,
            'status_id' => 4,
        ])->assertOk()->assertJson([
            'order_status' => 'completed',
            'shipping_status' => 'delivered',
        ]);

        $this->assertSame('delivered', $this->order->fresh()->shipping_status);
    }
}
