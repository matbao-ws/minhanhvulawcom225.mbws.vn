<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_code',
        'name',
        'account_name',
        'phone',
        'type',
        'status',
        'settings',
        'logo_url',
    ];

    protected $casts = [
        'settings' => 'array',
    ];
}
