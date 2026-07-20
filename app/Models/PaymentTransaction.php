<?php

namespace App\Models;

use App\Casts\EncryptedJson;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'payment_method',
        'gateway',
        'transaction_reference',
        'gateway_transaction_id',
        'amount',
        'status',
        'response_code',
        'idempotency_key',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => EncryptedJson::class,
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
