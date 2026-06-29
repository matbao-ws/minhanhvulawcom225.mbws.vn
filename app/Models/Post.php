<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Post extends Model
{
    use HasFactory, HasTranslations;

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
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }
}
