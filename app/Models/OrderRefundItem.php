<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRefundItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_refund_id',
        'order_item_id',
        'quantity',
        'amount',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function refund()
    {
        return $this->belongsTo(OrderRefund::class, 'order_refund_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
