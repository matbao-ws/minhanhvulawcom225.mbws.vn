<?php

namespace Tests\Feature;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_vnpay_ipn_records_one_encrypted_transaction_and_is_idempotent(): void
    {
        Mail::fake();
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
        $order = Order::query()->create([
            'order_number' => 'ORD-VNPAY-001',
            'customer_name' => 'VNPay Customer',
            'customer_email' => 'vnpay@example.com',
            'customer_phone' => '0900000000',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'vnpay',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 150000,
            'discount' => 0,
            'grand_total' => 150000,
        ]);
        $params = $this->signedVnpayParams($order);

        $this->getJson('/api/public/payment/vnpay/ipn?'.http_build_query($params))
            ->assertOk()
            ->assertJson(['RspCode' => '00']);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'vnpay',
            'gateway_transaction_id' => 'VNPAY-TRANSACTION-001',
            'status' => 'paid',
        ]);
        $transaction = PaymentTransaction::query()->firstOrFail();
        $this->assertArrayNotHasKey('vnp_SecureHash', $transaction->payload);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'pending',
            'to_status' => 'processing',
            'from_payment_status' => 'pending',
            'to_payment_status' => 'paid',
        ]);
        Mail::assertSent(OrderStatusMail::class, fn (OrderStatusMail $mail) => $mail->hasTo('vnpay@example.com'));

        $this->getJson('/api/public/payment/vnpay/ipn?'.http_build_query($params))
            ->assertOk()
            ->assertJson(['RspCode' => '02']);

        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseCount('order_status_histories', 1);
    }

    public function test_vnpay_ipn_cannot_update_an_order_using_a_different_payment_method(): void
    {
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
        $order = Order::query()->create([
            'order_number' => 'ORD-COD-001',
            'customer_name' => 'COD Customer',
            'customer_email' => 'cod@example.com',
            'customer_phone' => '0900000001',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 150000,
            'discount' => 0,
            'grand_total' => 150000,
        ]);

        $this->getJson('/api/public/payment/vnpay/ipn?'.http_build_query($this->signedVnpayParams($order)))
            ->assertOk()
            ->assertJson(['RspCode' => '02']);

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    private function signedVnpayParams(Order $order): array
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
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
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
}
