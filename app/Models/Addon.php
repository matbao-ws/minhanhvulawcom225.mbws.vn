<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'price',
        'description',
        'is_purchased',
    ];

    protected $casts = [
        'price' => 'float',
        'is_purchased' => 'boolean',
    ];

}
