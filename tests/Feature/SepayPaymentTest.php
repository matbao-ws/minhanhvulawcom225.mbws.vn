<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Services\SePayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SepayPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT = '0123456789';

    private const TOKEN = 'sepay-webhook-token-abcdefghijklmnop';

    private const SECRET = 'sepay-hmac-secret-qrstuvwxyz';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_webhook_settles_the_order_and_records_one_transaction(): void
    {
        $this->configureSepay();
        $order = $this->createSepayOrder('ORD-SEPAYAAA01', 250000);

        $this->postWithToken($this->payload($order, 250000))
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'sepay',
            'gateway_transaction_id' => '92704',
            'status' => 'paid',
            'response_code' => 'paid',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_payment_status' => 'paid',
            'note' => 'Cập nhật thanh toán từ webhook SePay',
        ]);
    }

    public function test_webhook_is_idempotent_for_a_redelivered_transaction(): void
    {
        $this->configureSepay();
        $order = $this->createSepayOrder('ORD-SEPAYAAA02', 250000);
        $payload = $this->payload($order, 250000);

        $this->postWithToken($payload)->assertOk();
        $this->postWithToken($payload)->assertOk()->assertExactJson(['success' => true]);

        $this->assertSame(1, $this->webhookTransactions()->count());
        $this->assertSame(1, $order->historyEntries()->where('to_payment_status', 'paid')->count());
    }

    public function test_webhook_rejects_a_wrong_or_missing_api_key(): void
    {
        $this->configureSepay();
        $order = $this->createSepayOrder('ORD-SEPAYAAA03', 250000);

        $this->postJson('/api/webhooks/sepay', $this->payload($order, 250000))->assertStatus(401);
        $this->withHeader('Authorization', 'Apikey wrong-token')
            ->postJson('/api/webhooks/sepay', $this->payload($order, 250000))
            ->assertStatus(401);

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_transactions', 1); // only the pending one
    }

    public function test_webhook_verifies_an_hmac_signature_and_rejects_a_forged_one(): void
    {
        $this->configureSepay(['webhook_secret' => self::SECRET]);
        $order = $this->createSepayOrder('ORD-SEPAYAAA04', 250000);
        $payload = $this->payload($order, 250000);
        $body = json_encode($payload);
        $timestamp = (string) now()->getTimestamp();

        $this->call('POST', '/api/webhooks/sepay', [], [], [], $this->signatureHeaders($timestamp, hash_hmac('sha256', $timestamp.'.'.$body, 'wrong-secret')), $body)
            ->assertStatus(401);
        $this->assertSame('pending', $order->fresh()->payment_status);

        $this->call('POST', '/api/webhooks/sepay', [], [], [], $this->signatureHeaders($timestamp, hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET)), $body)
            ->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_webhook_rejects_a_stale_signature_timestamp(): void
    {
        $this->configureSepay(['webhook_secret' => self::SECRET]);
        $order = $this->createSepayOrder('ORD-SEPAYAAA05', 250000);
        $body = json_encode($this->payload($order, 250000));
        $timestamp = (string) now()->subHour()->getTimestamp();

        $this->call('POST', '/api/webhooks/sepay', [], [], [], $this->signatureHeaders($timestamp, hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET)), $body)
            ->assertStatus(401);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_underpayment_records_the_transfer_without_settling_the_order(): void
    {
        $this->configureSepay();
        $order = $this->createSepayOrder('ORD-SEPAYAAA06', 250000);

        $this->postWithToken($this->payload($order, 200000))
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'sepay',
            'status' => 'pending',
            'response_code' => 'underpaid',
        ]);
    }

    public function test_overpayment_still_settles_the_order_and_keeps_the_real_amount(): void
    {
        $this->configureSepay();
        $order = $this->createSepayOrder('ORD-SEPAYAAA07', 250000);

        $this->postWithToken($this->payload($order, 300000))->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $transaction = $this->webhookTransactions()->firstOrFail();
        $this->assertSame('300000.00', (string) $transaction->amount);
    }

    public function test_outgoing_transfer_and_foreign_account_never_settle_an_order(): void
    {
        $this->configureSepay();
        $order = $this->createSepayOrder('ORD-SEPAYAAA08', 250000);

        $this->postWithToken(['transferType' => 'out'] + $this->payload($order, 250000))->assertOk();
        $this->postWithToken(['accountNumber' => '9999999999', 'id' => 92705] + $this->payload($order, 250000))->assertOk();

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, $this->webhookTransactions()->count());
    }

    public function test_payment_code_is_matched_from_free_text_transfer_content(): void
    {
        $this->configureSepay(['payment_prefix' => 'SEVQR']);
        $order = $this->createSepayOrder('ORD-SEPAYAAA09', 250000);

        // What a bank actually delivers: no separators, surrounded by noise.
        $payload = $this->payload($order, 250000);
        $payload['code'] = null;
        $payload['content'] = 'CT DEN:123 SEVQR ORDSEPAYAAA09 NGUYEN VAN A chuyen tien';

        $this->postWithToken($payload)->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_webhook_acknowledges_a_transfer_with_no_matching_order(): void
    {
        $this->configureSepay();
        $payload = $this->payload(null, 250000);
        $payload['code'] = null;
        $payload['content'] = 'NGUYEN VAN A chuyen tien';

        $this->postWithToken($payload)->assertOk()->assertExactJson(['success' => true]);

        $this->assertSame(0, $this->webhookTransactions()->count());
    }

    public function test_webhook_does_not_settle_a_cancelled_order(): void
    {
        $this->configureSepay();
        $order = $this->createSepayOrder('ORD-SEPAYAAA10', 250000);
        $order->update(['status' => 'cancelled']);

        $this->postWithToken($this->payload($order, 250000))->assertOk();

        $order->refresh();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
    }

    public function test_webhook_is_unavailable_while_the_gateway_is_inactive(): void
    {
        $this->configureSepay(['status' => 'inactive']);
        $order = $this->createSepayOrder('ORD-SEPAYAAA11', 250000);

        $this->postWithToken($this->payload($order, 250000))->assertStatus(503);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_stored_payload_keeps_only_documented_fields(): void
    {
        $this->configureSepay();
        $order = $this->createSepayOrder('ORD-SEPAYAAA12', 250000);

        $this->postWithToken($this->payload($order, 250000) + ['injected' => 'nope'])->assertOk();

        $transaction = $this->webhookTransactions()->firstOrFail();
        $this->assertArrayNotHasKey('injected', $transaction->payload);
        $this->assertSame('FT24012345678', $transaction->payload['referenceCode']);
    }

    public function test_qr_url_targets_the_fixed_sepay_host_with_the_order_amount_and_code(): void
    {
        $this->configureSepay(['payment_prefix' => 'SEVQR']);
        $order = $this->createSepayOrder('ORD-SEPAYAAA13', 250000);

        $url = app(SePayService::class)->qrImageUrl($order);

        $this->assertStringStartsWith('https://qr.sepay.vn/img?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(self::ACCOUNT, $query['acc']);
        $this->assertSame('MBBank', $query['bank']);
        $this->assertSame('250000', $query['amount']);
        $this->assertSame('SEVQRORDSEPAYAAA13', $query['des']);
    }

    public function test_checkout_instructions_never_expose_gateway_secrets(): void
    {
        $this->configureSepay(['webhook_secret' => self::SECRET, 'api_key' => 'sepay-api-token']);
        $order = $this->createSepayOrder('ORD-SEPAYAAA14', 250000);

        $encoded = json_encode(app(SePayService::class)->checkoutInstructions($order));

        $this->assertStringNotContainsString(self::TOKEN, $encoded);
        $this->assertStringNotContainsString(self::SECRET, $encoded);
        $this->assertStringNotContainsString('sepay-api-token', $encoded);
        $this->assertStringContainsString('ORDSEPAYAAA14', $encoded);
    }

    public function test_checkout_returns_payment_instructions_and_the_webhook_settles_that_order(): void
    {
        $this->enableFeatures();
        $this->configureSepay();
        $product = $this->createProduct();

        $response = $this->postJson('/api/public/orders/checkout', [
            'customer_name' => 'SePay Customer',
            'customer_email' => 'sepay-checkout@example.com',
            'customer_phone' => '0988776655',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'sepay',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk();

        $order = Order::query()->where('customer_email', 'sepay-checkout@example.com')->firstOrFail();
        $payment = $response->json('data.payment');
        $this->assertSame(str_replace('-', '', $order->order_number), $payment['payment_code']);
        $this->assertSame((int) round($order->grand_total), $payment['amount']);
        $this->assertSame(self::ACCOUNT, $payment['account_number']);
        $this->assertStringStartsWith('https://qr.sepay.vn/img?', $payment['qr_image_url']);
        $this->assertArrayNotHasKey('webhook_token', $payment);

        // Round-trip: the code the customer is told to transfer resolves back to the order.
        $this->postWithToken($this->payload($order, (int) round($order->grand_total)))->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_checkout_refuses_sepay_before_it_is_configured(): void
    {
        $this->enableFeatures();
        $this->configureSepay(['bank_code' => '', 'account_number' => '']);
        $product = $this->createProduct();

        $this->postJson('/api/public/orders/checkout', [
            'customer_name' => 'SePay Customer',
            'customer_email' => 'sepay-unconfigured@example.com',
            'customer_phone' => '0988776655',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'sepay',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422);

        // No order, and therefore no stock movement, for an unpayable gateway.
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_admin_cannot_activate_sepay_until_it_is_configured(): void
    {
        $this->enableFeatures();
        $role = \App\Models\Role::query()->create(['name' => 'Admin', 'permissions' => ['payments.view', 'payments.update']]);
        $admin = \App\Models\User::factory()->create(['role_id' => $role->id]);
        $method = $this->configureSepay(['status' => 'inactive', 'bank_code' => '', 'account_number' => '', 'webhook_token' => '']);

        $this->actingAs($admin)
            ->post("/vi/admin/payment-methods/{$method->id}/toggle-status")
            ->assertOk()
            ->assertJson(['success' => false]);
        $this->assertSame('inactive', $method->fresh()->status);

        $this->configureSepay(['status' => 'inactive']);
        $this->actingAs($admin)
            ->post("/vi/admin/payment-methods/{$method->id}/toggle-status")
            ->assertOk()
            ->assertJson(['success' => true]);
        $this->assertSame('active', $method->fresh()->status);
    }

    private function enableFeatures(): void
    {
        foreach (['catalog', 'cart', 'cod_order', 'online_payment'] as $featureCode) {
            \App\Models\FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $featureCode],
                ['is_enabled' => true],
            );
        }
    }

    private function createProduct(): \App\Models\Product
    {
        return \App\Models\Product::query()->create([
            'name' => ['vi' => 'Sản phẩm SePay', 'en' => 'SePay Product'],
            'slug' => 'san-pham-sepay',
            'sku' => 'SEPAY-PROD',
            'price' => 250000.00,
            'stock_quantity' => 5,
            'manage_stock' => true,
            'is_active' => true,
        ]);
    }

    /** Only the rows the webhook wrote; checkout opens a pending one on the same gateway. */
    private function webhookTransactions(): \Illuminate\Database\Eloquent\Builder
    {
        return PaymentTransaction::query()->where('idempotency_key', 'like', 'sepay:webhook:%');
    }

    private function configureSepay(array $overrides = []): PaymentMethod
    {
        $status = $overrides['status'] ?? 'active';
        unset($overrides['status']);

        return PaymentMethod::query()->updateOrCreate(['method_code' => 'sepay'], [
            'name' => 'SePay',
            'type' => 'connected',
            'status' => $status,
            'settings' => array_merge([
                'bank_code' => 'MBBank',
                'account_number' => self::ACCOUNT,
                'account_holder' => 'NGUYEN VAN A',
                'api_key' => '',
                'webhook_token' => self::TOKEN,
                'webhook_secret' => '',
                'payment_prefix' => '',
                'qr_template' => 'compact',
                'instructions' => '',
            ], $overrides),
        ]);
    }

    private function createSepayOrder(string $orderNumber, float $total): Order
    {
        $order = Order::query()->create([
            'order_number' => $orderNumber,
            'customer_name' => 'SePay Customer',
            'customer_email' => 'sepay@example.com',
            'customer_phone' => '0900000000',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'sepay',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => $total,
            'discount' => 0,
            'grand_total' => $total,
        ]);

        app(\App\Services\PaymentTransactionService::class)->createPending($order, 'test');

        return $order;
    }

    private function payload(?Order $order, int $amount): array
    {
        return [
            'id' => 92704,
            'gateway' => 'MBBank',
            'transactionDate' => '2026-08-17 11:08:33',
            'accountNumber' => self::ACCOUNT,
            'subAccount' => '',
            'code' => $order ? str_replace('-', '', $order->order_number) : null,
            'content' => $order ? str_replace('-', '', $order->order_number).' chuyen tien' : 'chuyen tien',
            'transferType' => 'in',
            'description' => 'NGUYEN VAN A chuyen tien',
            'transferAmount' => $amount,
            'accumulated' => 105000000,
            'referenceCode' => 'FT24012345678',
        ];
    }

    private function postWithToken(array $payload)
    {
        return $this->withHeader('Authorization', 'Apikey '.self::TOKEN)
            ->postJson('/api/webhooks/sepay', $payload);
    }

    private function signatureHeaders(string $timestamp, string $signature): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SEPAY_SIGNATURE' => 'sha256='.$signature,
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
        ];
    }
}
