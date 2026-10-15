<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedSlugs;
use App\Models\Concerns\HasSeoBox;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class PostCategory extends Model
{
    use HasFactory, HasLocalizedSlugs, HasSeoBox, HasTranslations;

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
        'is_active',
        'sort_order',
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'seo_score' => 'integer',
        'robots_index' => 'boolean',
        'robots_follow' => 'boolean',
        'schema_faq' => 'boolean',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
