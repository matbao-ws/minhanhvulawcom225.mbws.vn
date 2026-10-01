<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureSetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'feature_code',
        'is_enabled',
        'package_enabled',
        'is_overridden',
        'limit_value',
        'config',
        'updated_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'package_enabled' => 'boolean',
        'is_overridden' => 'boolean',
        'config' => 'array',
        'updated_at' => 'datetime',
    ];
}
