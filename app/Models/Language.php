<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'native_name',
        'regional',
        'flag_path',
        'is_active',
        'is_default',
        'is_content_fallback',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_content_fallback' => 'boolean',
        'sort_order' => 'integer',
    ];
}
