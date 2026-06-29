<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'used_count' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Check if the voucher is valid and can be applied to an order.
     */
    public function isValidForOrder(float $orderSubtotal): bool
    {
        if (!$this->is_active) {
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

        return true;
    }

    /**
     * Calculate discount amount for a given order subtotal.
     */
    public function calculateDiscount(float $orderSubtotal): float
    {
        if (!$this->isValidForOrder($orderSubtotal)) {
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
