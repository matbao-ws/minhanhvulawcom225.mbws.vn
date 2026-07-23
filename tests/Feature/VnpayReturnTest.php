<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VnpayReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::query()->updateOrCreate(['method_code' => 'vnpay'], [
            'name' => 'VNPAY',
            'type' => 'connected',
            'status' => 'active',
            'settings' => [
                'tmn_code' => 'mock',
                'hash_secret' => 'mock-secret',
                'api_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            ],
        ]);
    }

    private function makeOrder(): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-RET-001',
            'customer_name' => 'Return Customer',
            'customer_email' => 'return@example.com',
            'customer_phone' => '0900000000',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'vnpay',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 150000,
            'discount' => 0,
            'grand_total' => 150000,
        ]);
    }

    private function signedVnpayParams(Order $order, string $responseCode = '00'): array
    {
        $params = [
            'vnp_TmnCode' => 'mock',
            'vnp_Amount' => (int) $order->grand_total * 100,
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => '20260718120000',
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toan don hang '.$order->order_number,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => 'https://example.test/payment-return',
            'vnp_TxnRef' => $order->order_number,
            'vnp_Version' => '2.1.0',
            'vnp_ResponseCode' => $responseCode,
            'vnp_TransactionStatus' => $responseCode === '00' ? '00' : '02',
            'vnp_TransactionNo' => 'VNPAY-TRANSACTION-001',
        ];
        ksort($params);

        $hashData = '';
        foreach ($params as $key => $value) {
            $hashData .= $hashData === '' ? '' : '&';
            $hashData .= urlencode($key).'='.urlencode((string) $value);
        }
        $params['vnp_SecureHash'] = hash_hmac('sha512', $hashData, 'mock-secret');

        return $params;
    }

    public function test_valid_return_reports_success_without_mutating_the_order(): void
    {
        $order = $this->makeOrder();

        $this->getJson('/api/public/payment/vnpay/return?'.http_build_query($this->signedVnpayParams($order)))
            ->assertOk()
            ->assertJsonFragment(['payment' => 'success'])
            ->assertJsonPath('data.order.order_number', 'ORD-RET-001');

        // The return endpoint never changes payment state — the IPN is authoritative.
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_failed_response_code_is_reported_but_order_stays_pending(): void
    {
        $order = $this->makeOrder();

        $this->getJson('/api/public/payment/vnpay/return?'.http_build_query($this->signedVnpayParams($order, '24')))
            ->assertOk()
            ->assertJsonFragment(['payment' => 'failed']);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->makeOrder();
        $params = $this->signedVnpayParams($order);
        $params['vnp_SecureHash'] = 'tampered';

        $this->getJson('/api/public/payment/vnpay/return?'.http_build_query($params))
            ->assertStatus(400)
            ->assertJsonFragment(['payment' => 'invalid']);
    }

    public function test_extra_query_params_do_not_break_verification(): void
    {
        $order = $this->makeOrder();
        $params = $this->signedVnpayParams($order);
        // A non-vnp_ param (as carried on the return URL) must not poison the hash.
        $params['some_tracking'] = 'abc123';

        $this->getJson('/api/public/payment/vnpay/return?'.http_build_query($params))
            ->assertOk()
            ->assertJsonFragment(['payment' => 'success']);
    }

    public function test_return_redirects_to_validated_client_url_with_sanitized_status(): void
    {
        $order = $this->makeOrder();
        $params = $this->signedVnpayParams($order);
        $params['redirect_url'] = 'http://localhost/checkout/result';

        $response = $this->get('/api/public/payment/vnpay/return?'.http_build_query($params));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('http://localhost/checkout/result', $location);
        $this->assertStringContainsString('payment=success', $location);
        $this->assertStringContainsString('order_number=ORD-RET-001', $location);
    }
}
