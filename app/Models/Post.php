<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedSlugs;
use App\Models\Concerns\HasMediaUrls;
use App\Models\Concerns\HasSeoBox;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Post extends Model
{
    use HasFactory, HasLocalizedSlugs, HasMediaUrls, HasSeoBox, HasTranslations;

    public array $translatable = [
        'title',
        'summary',
        'content',
        'seo_title',
        'seo_description',
        'og_title',
        'og_description',
    ];

    /** schema.org types a post can be published as (the SEO box's Schema tab). */
    public const SCHEMA_TYPES = ['Article', 'BlogPosting', 'NewsArticle'];

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'summary',
        'content',
        'image_url',
        'is_active',
        'seo_title',
        'seo_description',
        'seo_keys',
        'og_title',
        'og_description',
        'og_image',
        'schema_type',
        'schema_faq',
        'canonical_url',
        'robots_index',
        'robots_follow',
        'seo_score',
        'seo_analysis',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'robots_index' => 'boolean',
        'robots_follow' => 'boolean',
        'schema_faq' => 'boolean',
        'seo_score' => 'integer',
        'seo_analysis' => 'array',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }
}
