<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'package_name',
        'amount',
        'status',
        'billing_date',
        'due_date',
        'payment_method',
        'addon_code',
        'sepay_transaction_id',
    ];

    protected $casts = [
        'billing_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function addon()
    {
        return $this->belongsTo(Addon::class, 'addon_code', 'code');
    }
}
