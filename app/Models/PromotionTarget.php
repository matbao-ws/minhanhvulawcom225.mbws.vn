<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionTarget extends Model
{
    protected $fillable = [
        'promotion_id', 'product_id', 'product_variant_id', 'quantity_limit', 'used_count',
    ];

    protected $casts = [
        'quantity_limit' => 'integer',
        'used_count' => 'integer',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function canReserve(int $quantity): bool
    {
        return $this->quantity_limit === null || $this->used_count + $quantity <= $this->quantity_limit;
    }
}
