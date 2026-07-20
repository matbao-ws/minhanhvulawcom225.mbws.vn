<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'order_id',
        'order_item_id',
        'action',
        'direction',
        'quantity',
        'product_stock_after',
        'variant_stock_after',
        'idempotency_key',
        'note',
        'metadata',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'product_stock_after' => 'integer',
        'variant_stock_after' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
