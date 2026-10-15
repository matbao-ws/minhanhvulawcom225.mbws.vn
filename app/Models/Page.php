<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedSlugs;
use App\Models\Concerns\HasSeoBox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
    use HasLocalizedSlugs, HasSeoBox, HasTranslations, SoftDeletes;

    public array $translatable = [
        'title',
        'published_html',
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
    ];

    protected $fillable = [
        'title',
        'slug',
        'published_html',
        'meta_title',
        'meta_description',
        'seo_keys',
        'seo_score',
        'canonical_url',
        'robots_index',
        'robots_follow',
        'og_title',
        'og_description',
        'og_image',
        'schema_faq',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'seo_score' => 'integer',
        'robots_index' => 'boolean',
        'robots_follow' => 'boolean',
        'schema_faq' => 'boolean',
    ];

    public function revisions()
    {
        return $this->hasMany(PageRevision::class)->latest('created_at');
    }
}
