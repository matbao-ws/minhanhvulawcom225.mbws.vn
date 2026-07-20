<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'payment_method',
        'shipping_carrier',
        'shipping_fee',
        'tracking_number',
        'payment_status',
        'status',
        'subtotal',
        'discount',
        'promotion_discount',
        'grand_total',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'promotion_discount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function historyEntries()
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('created_at');
    }

    public function refunds()
    {
        return $this->hasMany(OrderRefund::class)->latest();
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class)->latest('created_at');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class)->latest();
    }
}
