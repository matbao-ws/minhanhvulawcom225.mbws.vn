<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedSlugs;
use App\Models\Concerns\HasMediaUrls;
use App\Models\Concerns\HasSeoBox;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasLocalizedSlugs, HasMediaUrls, HasSeoBox, HasTranslations;

    public array $translatable = [
        'name',
        'description',
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
    ];

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'meta_title',
        'meta_description',
        'image_url',
        'sort_order',
        'is_active',
        'is_draft',
        'seo_keys',
        'seo_score',
        'canonical_url',
        'robots_index',
        'robots_follow',
        'og_title',
        'og_description',
        'og_image',
        'schema_faq',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_draft' => 'boolean',
        'sort_order' => 'integer',
        'seo_score' => 'integer',
        'robots_index' => 'boolean',
        'robots_follow' => 'boolean',
        'schema_faq' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
