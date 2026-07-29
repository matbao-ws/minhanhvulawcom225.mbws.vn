<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'created_by',
        'schema_version',
        'builder_data',
        'published_html',
        'published_css',
    ];

    protected $casts = [
        'builder_data' => 'array',
        'published_html' => 'array',
        'published_css' => 'array',
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
