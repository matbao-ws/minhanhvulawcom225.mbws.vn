<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShippingPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * GHN can only send its token in the query string, so the callback must not
 * reach further than the shipment it is about.
 */
class GhnWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'ghn-webhook-token-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        ShippingPartner::query()->where('partner_code', 'DTGH000013')->firstOrFail()->update([
            'status' => 'active',
            'settings' => ['api_token' => 'x', 'shop_id' => '1', 'webhook_token' => self::TOKEN, 'realtime_tracking_enabled' => true],
        ]);
    }

    public function test_a_callback_without_the_token_is_rejected(): void
    {
        $this->postJson('/api/webhooks/ghn?token=wrong', ['OrderCode' => 'GHN1', 'Status' => 'delivered'])->assertUnauthorized();
    }

    public function test_a_callback_updates_the_order_it_ships(): void
    {
        $order = $this->order('ORD-GHNSHIP001', ['shipping_carrier' => 'ghn', 'tracking_number' => 'GHNAAA', 'status' => 'processing', 'shipping_status' => 'in_transit']);

        $this->postJson('/api/webhooks/ghn?token='.self::TOKEN, ['OrderCode' => 'GHNAAA', 'Status' => 'delivered'])->assertOk();

        $this->assertSame('delivered', $order->fresh()->shipping_status);
    }

    public function test_our_order_number_reaches_an_order_no_carrier_ships_yet(): void
    {
        $order = $this->order('ORD-GHNDASH001', ['status' => 'processing']);

        $this->postJson('/api/webhooks/ghn?token='.self::TOKEN, ['OrderCode' => 'GHNBBB', 'ClientOrderCode' => $order->order_number, 'Status' => 'picked'])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame('GHNBBB', $fresh->tracking_number);
        $this->assertSame('ghn', $fresh->shipping_carrier);
    }

    public function test_our_order_number_cannot_take_over_another_carriers_order(): void
    {
        $order = $this->order('ORD-GHTKOWN001', ['shipping_carrier' => 'ghtk', 'tracking_number' => 'S1.A1.123', 'status' => 'processing', 'shipping_status' => 'in_transit']);

        $this->postJson('/api/webhooks/ghn?token='.self::TOKEN, ['OrderCode' => 'GHNCCC', 'ClientOrderCode' => $order->order_number, 'Status' => 'cancel'])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame('ghtk', $fresh->shipping_carrier);
        $this->assertSame('S1.A1.123', $fresh->tracking_number);
        $this->assertSame('processing', $fresh->status);
    }

    private function order(string $number, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => $number,
            'customer_name' => 'Khách',
            'customer_email' => 'khach@example.com',
            'customer_phone' => '0900000000',
            'shipping_address' => 'HCM',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000,
            'discount' => 0,
            'grand_total' => 100000,
        ], $overrides));
    }
}
