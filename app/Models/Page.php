<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedSlugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
    use HasLocalizedSlugs, HasTranslations, SoftDeletes;

    public array $translatable = [
        'title',
        'published_html',
        'published_css',
        'meta_title',
        'meta_description',
    ];

    protected $fillable = [
        'title',
        'slug',
        'schema_version',
        'builder_data',
        'published_html',
        'published_css',
        'meta_title',
        'meta_description',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'builder_data' => 'array',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function revisions()
    {
        return $this->hasMany(PageRevision::class)->latest('created_at');
    }
}
