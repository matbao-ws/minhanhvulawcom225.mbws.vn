<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'created_by',
        'published_html',
    ];

    protected $casts = [
        'published_html' => 'array',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
