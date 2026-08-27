<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

/**
 * SePay integration.
 *
 * SePay is not a redirect gateway like VNPAY: it watches a real bank account and
 * pushes every incoming transfer to us as a webhook. The customer pays by scanning
 * a VietQR image that pre-fills our account, the exact amount and a payment code;
 * the webhook that follows is what proves the money arrived.
 *
 * Consequence for the money invariants: the webhook is the single source of truth
 * for payment state, exactly as the VNPAY IPN is. Nothing the storefront reports
 * back may change an order's payment status.
 */
class SePayService
{
    public const METHOD_CODE = 'sepay';

    /**
     * Fixed host. The merchant configures an account number and a bank code, never
     * a URL, so no operator input can point QR generation at another host.
     */
    private const QR_ENDPOINT = 'https://qr.sepay.vn/img';

    public const QR_TEMPLATES = ['compact', 'compact2', 'qronly', 'print'];

    /** How far the HMAC timestamp may drift before we treat the call as a replay. */
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    /** Fields SePay documents. Anything else in the body is dropped before we store it. */
    private const PAYLOAD_FIELDS = [
        'id', 'gateway', 'transactionDate', 'accountNumber', 'subAccount', 'code',
        'content', 'transferType', 'description', 'transferAmount', 'accumulated',
        'referenceCode',
    ];

    public function method(): ?PaymentMethod
    {
        return PaymentMethod::query()->where('method_code', self::METHOD_CODE)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(?PaymentMethod $method = null): array
    {
        return ($method ?? $this->method())?->settings ?? [];
    }

    /**
     * Everything the checkout and the webhook need is present.
     *
     * Deliberately the same predicate the admin activation check uses, so an
     * activated method can always build a QR and always authenticates its webhook.
     */
    public function isConfigured(?PaymentMethod $method = null): bool
    {
        $settings = $this->settings($method);

        return $this->setting($settings, 'account_number') !== ''
            && $this->setting($settings, 'bank_code') !== ''
            && ($this->setting($settings, 'webhook_token') !== '' || $this->setting($settings, 'webhook_secret') !== '');
    }

    public function isEnabled(): bool
    {
        $method = $this->method();

        return $method?->status === 'active' && $this->isConfigured($method);
    }

    /**
     * Optional merchant prefix, matching the "mã thanh toán" prefix configured on the
     * SePay side. When set, SePay extracts it into the webhook's `code` field.
     */
    public function paymentPrefix(?PaymentMethod $method = null): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($this->setting($this->settings($method), 'payment_prefix')));
    }

    public function qrTemplate(?PaymentMethod $method = null): string
    {
        $template = $this->setting($this->settings($method), 'qr_template');

        return in_array($template, self::QR_TEMPLATES, true) ? $template : 'compact';
    }

    /**
     * The string the customer transfers as the content of their bank transfer.
     *
     * Banks strip punctuation from transfer content, so the separator in the order
     * number is dropped here and restored in resolveOrder().
     */
    public function paymentCode(Order $order, ?PaymentMethod $method = null): string
    {
        return $this->paymentPrefix($method).str_replace('-', '', $order->order_number);
    }

    public function qrImageUrl(Order $order, ?PaymentMethod $method = null): ?string
    {
        $method ??= $this->method();
        $settings = $this->settings($method);

        $account = $this->setting($settings, 'account_number');
        $bank = $this->setting($settings, 'bank_code');
        if ($account === '' || $bank === '') {
            return null;
        }

        return self::QR_ENDPOINT.'?'.http_build_query([
            'acc' => $account,
            'bank' => $bank,
            'amount' => (int) round($order->grand_total),
            'des' => $this->paymentCode($order, $method),
            'template' => $this->qrTemplate($method),
            'download' => 'false',
        ]);
    }

    /**
     * Public payment instructions returned by checkout.
     *
     * Payee details only — the API key, webhook token and webhook secret never
     * leave the admin screen.
     *
     * @return array<string, mixed>|null
     */
    public function checkoutInstructions(Order $order): ?array
    {
        $method = $this->method();
        $qrImageUrl = $this->qrImageUrl($order, $method);
        if ($qrImageUrl === null) {
            return null;
        }

        $settings = $this->settings($method);

        return [
            'gateway' => self::METHOD_CODE,
            'payment_code' => $this->paymentCode($order, $method),
            'amount' => (int) round($order->grand_total),
            'currency' => 'VND',
            'bank_code' => $this->setting($settings, 'bank_code'),
            'account_number' => $this->setting($settings, 'account_number'),
            'account_holder' => $this->setting($settings, 'account_holder'),
            'qr_image_url' => $qrImageUrl,
            'instructions' => $this->setting($settings, 'instructions'),
        ];
    }

    /**
     * Authenticate an incoming webhook.
     *
     * The signature header selects the mode: when SePay signs the call we verify the
     * HMAC and nothing else; otherwise we fall back to the API key. An unconfigured
     * secret is never treated as "no authentication needed".
     */
    public function verifyWebhookRequest(Request $request, ?PaymentMethod $method = null): bool
    {
        $settings = $this->settings($method ?? $this->method());

        if ($request->hasHeader('X-SePay-Signature')) {
            $secret = $this->setting($settings, 'webhook_secret');

            return $secret !== '' && $this->verifySignature($request, $secret);
        }

        $token = $this->setting($settings, 'webhook_token');

        return $token !== '' && $this->verifyApiKey($request, $token);
    }

    /**
     * The transfer landed on the account this site is configured for.
     *
     * One SePay account can serve several bank accounts and several sites; without
     * this check a transfer belonging to another site would settle orders here.
     */
    public function matchesConfiguredAccount(array $payload, ?PaymentMethod $method = null): bool
    {
        $configured = $this->normalizeAccount($this->setting($this->settings($method), 'account_number'));
        if ($configured === '') {
            return false;
        }

        foreach (['accountNumber', 'subAccount'] as $field) {
            if ($this->normalizeAccount((string) ($payload[$field] ?? '')) === $configured) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the order a transfer pays for.
     *
     * SePay fills `code` when the merchant configured a payment-code prefix; when it
     * did not, the code is still somewhere inside the free-text transfer content. Both
     * are scanned for the order-number shape, then matched with an exact indexed
     * lookup rather than a normalising query.
     */
    public function resolveOrder(array $payload, ?PaymentMethod $method = null): ?Order
    {
        $candidates = $this->extractOrderNumbers($payload, $method);
        if ($candidates === []) {
            return null;
        }

        return Order::query()
            ->whereIn('order_number', $candidates)
            ->where('payment_method', self::METHOD_CODE)
            ->first();
    }

    /**
     * @return array<int, string>
     */
    public function extractOrderNumbers(array $payload, ?PaymentMethod $method = null): array
    {
        $prefix = $this->paymentPrefix($method);
        $body = preg_quote(rtrim(Order::NUMBER_PREFIX, '-'), '/');
        $pattern = '/'
            .($prefix !== '' ? '(?:'.preg_quote($prefix, '/').')?' : '')
            .'('.$body.'[A-Z0-9]{'.Order::NUMBER_RANDOM_LENGTH.'})/';

        $found = [];
        // Scanned field by field: concatenating them could fabricate a match across
        // the seam between two otherwise unrelated values.
        foreach (['code', 'content', 'description'] as $field) {
            $normalized = preg_replace('/[^A-Z0-9]+/', '', strtoupper((string) ($payload[$field] ?? '')));
            if ($normalized === '' || ! preg_match_all($pattern, $normalized, $matches)) {
                continue;
            }

            foreach ($matches[1] as $match) {
                $found[] = Order::NUMBER_PREFIX.substr($match, strlen(rtrim(Order::NUMBER_PREFIX, '-')));
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Keep only documented fields before the payload is persisted, so a caller cannot
     * grow the stored transaction with arbitrary data.
     *
     * @return array<string, mixed>
     */
    public static function redactPayload(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::PAYLOAD_FIELDS));
    }

    private function verifySignature(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-SePay-Signature');
        $timestamp = trim((string) $request->header('X-SePay-Timestamp'));

        if (! preg_match('/^sha256=([a-f0-9]{64})$/i', trim($signature), $matches)) {
            return false;
        }

        if (! ctype_digit($timestamp) || abs(now()->getTimestamp() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        // Signed over the raw body: re-encoding the parsed JSON would reorder keys
        // and change escaping, and the digest would never match.
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, strtolower($matches[1]));
    }

    private function verifyApiKey(Request $request, string $token): bool
    {
        $header = trim((string) $request->header('Authorization'));

        if (! preg_match('/^Apikey\s+(\S+)$/i', $header, $matches)) {
            return false;
        }

        return hash_equals($token, $matches[1]);
    }

    private function normalizeAccount(string $account): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', $account);
    }

    private function setting(array $settings, string $key): string
    {
        return trim((string) ($settings[$key] ?? ''));
    }
}
