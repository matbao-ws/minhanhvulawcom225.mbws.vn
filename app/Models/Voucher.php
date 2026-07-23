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
     * When $userId or $email is provided, the per-user usage limit is enforced.
     */
    public function isValidForOrder(float $orderSubtotal, ?int $userId = null, ?string $email = null): bool
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

        if ($this->reachedPerUserLimit($userId, $email)) {
            return false;
        }

        return true;
    }

    /**
     * Number of times a given customer (by account id, else email) has already used this voucher.
     */
    public function usageCountForCustomer(?int $userId = null, ?string $email = null): int
    {
        if ($userId === null && ($email === null || $email === '')) {
            return 0;
        }

        return $this->usages()
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->when($userId === null, fn ($q) => $q->whereRaw('LOWER(customer_email) = ?', [strtolower((string) $email)]))
            ->count();
    }

    /**
     * Whether the customer has exhausted their per-user allowance.
     * Anonymous callers (no id and no email) are never blocked by this rule.
     */
    public function reachedPerUserLimit(?int $userId = null, ?string $email = null): bool
    {
        if ($this->per_user_limit === null) {
            return false;
        }

        if ($userId === null && ($email === null || $email === '')) {
            return false;
        }

        return $this->usageCountForCustomer($userId, $email) >= $this->per_user_limit;
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
