<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;

class VNPAYService
{
    public static function allowedGatewayUrls(): array
    {
        return [
            'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'https://pay.vnpay.vn/vpcpay.html',
        ];
    }

    /**
     * Create payment redirect URL for VNPAY.
     */
    public function createPayment(Order $order, ?string $redirectUrl = null): ?string
    {
        $paymentMethod = PaymentMethod::where('method_code', 'vnpay')->first();
        if (! $paymentMethod || $paymentMethod->status !== 'active') {
            return null;
        }

        $settings = $paymentMethod->settings;
        $tmnCode = $settings['tmn_code'] ?? '';
        $hashSecret = $settings['hash_secret'] ?? '';
        $apiUrl = $settings['api_url'] ?? '';

        if (empty($tmnCode) || empty($hashSecret) || ! in_array($apiUrl, self::allowedGatewayUrls(), true)) {
            return null;
        }

        $orderId = $order->order_number;
        $amount = (int) round($order->grand_total);
        $redirectUrl = $this->safeReturnUrl($redirectUrl);

        // If mock mode, return internal mock URL
        if ($tmnCode === 'mock') {
            // keep the original mock behaviour (returns to the client redirect directly)
            if (! config('app.payment_mock_enabled') || ! app()->environment(['local', 'testing'])) {
                return null;
            }

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
            'vnp_OrderInfo' => 'Thanh toan don hang '.$orderId,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $this->browserReturnUrl($redirectUrl),
            'vnp_TxnRef' => $orderId,
        ];

        ksort($ipnParams);
        $query = '';
        $i = 0;
        $hashData = '';

        foreach ($ipnParams as $key => $value) {
            if ($i == 1) {
                $hashData .= '&'.urlencode($key).'='.urlencode($value);
            } else {
                $hashData .= urlencode($key).'='.urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key).'='.urlencode($value).'&';
        }

        $vnpSecureHash = hash_hmac('sha512', $hashData, $hashSecret);
        $paymentUrl = $apiUrl.'?'.$query.'vnp_SecureHash='.$vnpSecureHash;

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
        if (! $paymentMethod) {
            return false;
        }

        $tmnCode = (string) ($paymentMethod->settings['tmn_code'] ?? '');
        $hashSecret = $paymentMethod->settings['hash_secret'] ?? '';
        if (empty($tmnCode) || empty($hashSecret) || ! hash_equals($tmnCode, (string) ($params['vnp_TmnCode'] ?? ''))) {
            return false;
        }

        // Filter and sort parameters
        $hashData = '';
        ksort($params);
        $i = 0;

        foreach ($params as $key => $value) {
            // Only the vnp_* fields are part of the signed data. The browser return URL
            // can carry extra query params (e.g. redirect_url) that must not poison the hash.
            if (! str_starts_with((string) $key, 'vnp_') || $key === 'vnp_SecureHash' || $key === 'vnp_SecureHashType') {
                continue;
            }
            if ($i == 1) {
                $hashData .= '&'.urlencode($key).'='.urlencode($value);
            } else {
                $hashData .= urlencode($key).'='.urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $hashSecret);

        return hash_equals($secureHash, $vnpSecureHash);
    }

    /**
     * The browser return URL VNPAY redirects the customer to after payment.
     *
     * Points at our own server endpoint (which verifies the signature) rather than
     * straight at the client app, carrying the validated client redirect along so the
     * server can bounce the customer back with a sanitized result.
     */
    public function browserReturnUrl(?string $clientRedirectUrl): string
    {
        $clientRedirect = $this->safeReturnUrl($clientRedirectUrl);

        $params = $clientRedirect !== url('/') ? ['redirect_url' => $clientRedirect] : [];

        return route('api.payment.vnpay.return', $params);
    }

    public function safeReturnUrl(?string $url): string
    {
        $fallback = url('/');
        if (! is_string($url) || $url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return $fallback;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $allowedHosts = array_map('strtolower', config('app.payment_return_hosts', []));
        if ($appHost !== '') {
            $allowedHosts[] = $appHost;
        }

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || ! in_array($host, array_unique($allowedHosts), true)) {
            return $fallback;
        }

        return $url;
    }
}
