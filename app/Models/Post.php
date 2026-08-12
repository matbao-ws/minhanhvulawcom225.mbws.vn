<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedSlugs;
use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Post extends Model
{
    use HasFactory, HasLocalizedSlugs, HasTranslations, HasMediaUrls;

    public array $translatable = [
        'title',
        'summary',
        'content',
        'seo_title',
        'seo_description',
    ];

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
        'seo_score' => 'integer',
        'seo_analysis' => 'array',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }
}
