<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'code',
        'name',
        'price',
        'description',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'package_features')
            ->withPivot(['is_enabled', 'limit_value', 'config'])
            ->withTimestamps();
    }
}
