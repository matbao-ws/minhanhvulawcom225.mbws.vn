<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'value_type',
    ];

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_features')
            ->withPivot(['is_enabled', 'limit_value', 'config'])
            ->withTimestamps();
    }
}
