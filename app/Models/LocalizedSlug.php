<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalizedSlug extends Model
{
    protected $fillable = [
        'locale',
        'slug',
        'is_current',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function sluggable()
    {
        return $this->morphTo();
    }
}
