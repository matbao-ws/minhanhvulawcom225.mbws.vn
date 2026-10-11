<?php

namespace App\Models;

use App\Casts\EncryptedJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'method_code',
        'name',
        'account_name',
        'type',
        'status',
        'settings',
        'logo_url',
    ];

    protected $casts = [
        'settings' => EncryptedJson::class,
    ];
}
