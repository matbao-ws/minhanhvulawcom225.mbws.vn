<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A checkout sent with an Idempotency-Key, and the order it created. */
class CheckoutRequest extends Model
{
    protected $fillable = ['scope', 'idempotency_key', 'fingerprint', 'order_id'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
