<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;

class VNPAYService
{
    /**
     * Create payment redirect URL for VNPAY.
     */
    public function createPayment(Order $order, string $redirectUrl = null): ?string
    {
        $paymentMethod = PaymentMethod::where('method_code', 'vnpay')->first();
        if (!$paymentMethod || $paymentMethod->status !== 'active') {
            return null;
        }

        $settings = $paymentMethod->settings;
        $tmnCode = $settings['tmn_code'] ?? '';
        $hashSecret = $settings['hash_secret'] ?? '';
        $apiUrl = $settings['api_url'] ?? '';

        if (empty($tmnCode) || empty($hashSecret) || empty($apiUrl)) {
            return null;
        }

        $orderId = $order->order_number;
        $amount = (int) round($order->grand_total);
        $redirectUrl = $redirectUrl ?: url('/');

        // If mock mode, return internal mock URL
        if ($tmnCode === 'mock') {
            return route('vnpay.mock', [
                'order_id' => $orderId,
                'amount' => $amount,
                'redirect_url' => $redirectUrl,
            ]);
        }

        $ipnParams = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => $amount * 100, // VNPAY expects amount in cents (multiplied by 100)
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => request()->ip() ?: '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toan don hang ' . $orderId,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $redirectUrl,
            'vnp_TxnRef' => $orderId,
        ];

        ksort($ipnParams);
        $query = "";
        $i = 0;
        $hashData = "";

        foreach ($ipnParams as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnpSecureHash = hash_hmac('sha512', $hashData, $hashSecret);
        $paymentUrl = $apiUrl . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

        return $paymentUrl;
    }

    /**
     * Verify VNPAY return / IPN signature.
     */
    public function verifyIpnSignature(array $params): bool
    {
        $vnpSecureHash = $params['vnp_SecureHash'] ?? '';
        if (empty($vnpSecureHash)) {
            return false;
        }

        $paymentMethod = PaymentMethod::where('method_code', 'vnpay')->first();
        if (!$paymentMethod) {
            return false;
        }

        $hashSecret = $paymentMethod->settings['hash_secret'] ?? '';
        if (empty($hashSecret)) {
            return false;
        }

        // Filter and sort parameters
        $hashData = "";
        ksort($params);
        $i = 0;

        foreach ($params as $key => $value) {
            if ($key === 'vnp_SecureHash' || $key === 'vnp_SecureHashType') {
                continue;
            }
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $hashSecret);

        return hash_equals($secureHash, $vnpSecureHash);
    }
}
