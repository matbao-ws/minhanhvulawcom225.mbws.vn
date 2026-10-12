<?php

namespace App\Services\Orders;

use App\Models\CheckoutRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Idempotency-Key for the public checkout: a retried checkout returns the order it
 * already created instead of a second one.
 *
 * Scope: `user:<id>` for a signed-in customer, `guest` otherwise. A guest has no
 * session, so the key itself is the secret. That is why guest keys must be long and
 * random (a UUID), and why a stored order is only returned to a request whose
 * fingerprint matches, which means it already carries that order's contact details.
 */
class CheckoutIdempotency
{
    public const HEADER = 'Idempotency-Key';

    private const PATTERN = '/^[A-Za-z0-9_-]{16,64}$/';

    private const GUEST_MIN_LENGTH = 32;

    /** The validation error for a key, or null when it can be used. */
    public function invalidKey(string $key, ?User $user): ?string
    {
        if (! preg_match(self::PATTERN, $key)) {
            return self::HEADER.' phải dài 16–64 ký tự, chỉ gồm chữ, số, "-" và "_".';
        }
        if ($user === null && strlen($key) < self::GUEST_MIN_LENGTH) {
            return self::HEADER.' của khách chưa đăng nhập phải dài ít nhất '.self::GUEST_MIN_LENGTH.' ký tự ngẫu nhiên (ví dụ một UUID).';
        }

        return null;
    }

    public function scope(?User $user): string
    {
        return $user !== null ? 'user:'.$user->id : 'guest';
    }

    /**
     * What the order is made of, normalized: the same cart and details give the same
     * fingerprint whatever the item order, spacing or letter case. redirect_url is left
     * out because it only says where to land after paying.
     */
    public function fingerprint(array $input): string
    {
        $text = fn ($value) => $value === null ? null : trim((string) $value);

        $items = array_map(function ($item) {
            $options = array_map('intval', (array) ($item['option_value_ids'] ?? []));
            sort($options);

            return [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                'option_value_ids' => $options,
                'quantity' => (int) ($item['quantity'] ?? 0),
            ];
        }, array_values((array) ($input['items'] ?? [])));
        usort($items, fn (array $a, array $b) => [$a['product_id'], $a['variant_id'], $a['option_value_ids']]
            <=> [$b['product_id'], $b['variant_id'], $b['option_value_ids']]);

        $voucher = $text($input['voucher_code'] ?? null);

        return hash('sha256', json_encode([
            'customer_name' => $text($input['customer_name'] ?? null),
            'customer_email' => mb_strtolower((string) $text($input['customer_email'] ?? null)),
            'customer_phone' => $text($input['customer_phone'] ?? null),
            'shipping_address' => $text($input['shipping_address'] ?? null),
            'payment_method' => $text($input['payment_method'] ?? null),
            'notes' => $text($input['notes'] ?? null) ?: null,
            'voucher_code' => $voucher ? strtoupper($voucher) : null,
            'ghn_province_id' => isset($input['ghn_province_id']) ? (int) $input['ghn_province_id'] : null,
            'ghn_district_id' => isset($input['ghn_district_id']) ? (int) $input['ghn_district_id'] : null,
            'ghn_ward_code' => $text($input['ghn_ward_code'] ?? null),
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE));
    }

    public function find(string $scope, string $key): ?CheckoutRequest
    {
        return CheckoutRequest::query()
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->first();
    }

    /**
     * Claims the key inside the checkout transaction, before any row lock. A concurrent
     * request with the same key waits on the unique index, then fails with a duplicate,
     * and its whole transaction rolls back.
     */
    public function claim(string $scope, string $key, string $fingerprint): CheckoutRequest
    {
        return CheckoutRequest::query()->create([
            'scope' => $scope,
            'idempotency_key' => $key,
            'fingerprint' => $fingerprint,
        ]);
    }

    /** Whether the violation is this table's key, not some other unique index. */
    public function isKeyCollision(UniqueConstraintViolationException $e): bool
    {
        return str_contains($e->getMessage(), 'checkout_requests');
    }
}
