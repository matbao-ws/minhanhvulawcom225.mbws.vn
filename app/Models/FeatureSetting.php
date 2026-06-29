<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureSetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'feature_code',
        'is_enabled',
        'limit_value',
        'config',
        'updated_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'array',
        'updated_at' => 'datetime',
    ];
}
