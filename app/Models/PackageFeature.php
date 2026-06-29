<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageFeature extends Model
{
    protected $fillable = [
        'package_id',
        'feature_id',
        'is_enabled',
        'limit_value',
        'config',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'array',
    ];
}
