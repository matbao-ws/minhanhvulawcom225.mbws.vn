<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Voucher extends Model
{
    use HasTranslations;

    public array $translatable = [
        'name',
        'description',
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'quantity',
        'per_user_limit',
        'used_count',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'quantity' => 'integer',
        'per_user_limit' => 'integer',
        'used_count' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    /**
     * Check if the voucher is valid and can be applied to an order.
     *
     * When $userId, $email or $phone is provided, the per-user usage limit is enforced.
     */
    public function isValidForOrder(float $orderSubtotal, ?int $userId = null, ?string $email = null, ?string $phone = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();
        if ($this->start_date && $this->start_date->isAfter($now)) {
            return false;
        }
        if ($this->end_date && $this->end_date->isBefore($now)) {
            return false;
        }

        if ($this->quantity !== null && $this->used_count >= $this->quantity) {
            return false;
        }

        if ($orderSubtotal < (float) $this->min_order_amount) {
            return false;
        }

        if ($this->reachedPerUserLimit($userId, $email, $phone)) {
            return false;
        }

        return true;
    }

    /**
     * Number of times a given customer has already used this voucher. A use counts
     * when it matches ANY of the identifiers: the account, the normalised email or
     * the normalised phone. Matching on one only let a member drop their token and
     * a guest add a "+tag" to their email to look like somebody new.
     */
    public function usageCountForCustomer(?int $userId = null, ?string $email = null, ?string $phone = null): int
    {
        $emailKey = self::emailKey($email);
        $phoneKey = self::phoneKey($phone);
        if ($userId === null && $emailKey === null && $phoneKey === null) {
            return 0;
        }

        return $this->usages()
            ->where(function ($query) use ($userId, $emailKey, $phoneKey) {
                $query->when($userId !== null, fn ($q) => $q->orWhere('user_id', $userId))
                    ->when($emailKey !== null, fn ($q) => $q->orWhere('customer_email_key', $emailKey))
                    ->when($phoneKey !== null, fn ($q) => $q->orWhere('customer_phone_key', $phoneKey));
            })
            ->count();
    }

    /**
     * Whether the customer has exhausted their per-user allowance.
     * Anonymous callers (no id, email or phone) are never blocked by this rule;
     * checkout always has an email and a phone.
     */
    public function reachedPerUserLimit(?int $userId = null, ?string $email = null, ?string $phone = null): bool
    {
        if ($this->per_user_limit === null) {
            return false;
        }

        return $this->usageCountForCustomer($userId, $email, $phone) >= $this->per_user_limit;
    }

    /**
     * One key per mailbox: lowercase, "+tag" dropped, and for Gmail the dots too,
     * since Gmail delivers a.b+x@gmail.com and ab@gmail.com to the same inbox.
     */
    public static function emailKey(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));
        if (! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = explode('+', $local, 2)[0];
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local === '' ? null : $local.'@'.$domain;
    }

    /** Digits only, with the Vietnamese country code folded into the leading 0. */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '84') && strlen($digits) >= 11) {
            $digits = '0'.substr($digits, 2);
        }

        return strlen($digits) >= 8 ? $digits : null;
    }

    /**
     * Calculate discount amount for a given order subtotal.
     */
    public function calculateDiscount(float $orderSubtotal): float
    {
        if (! $this->isValidForOrder($orderSubtotal)) {
            return 0.0;
        }

        $discount = 0.0;
        if ($this->type === 'percentage') {
            $discount = $orderSubtotal * ((float) $this->value / 100);
            if ($this->max_discount_amount && $discount > (float) $this->max_discount_amount) {
                $discount = (float) $this->max_discount_amount;
            }
        } else {
            $discount = (float) $this->value;
        }

        // Discount cannot exceed subtotal
        return min($discount, $orderSubtotal);
    }
}
