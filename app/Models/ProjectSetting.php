<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectSetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'updated_at',
    ];

    protected $casts = [
        'setting_value' => 'array',
        'updated_at' => 'datetime',
    ];
}
