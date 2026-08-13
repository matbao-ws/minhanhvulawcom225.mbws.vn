<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedSlugs;
use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasLocalizedSlugs, HasTranslations, HasMediaUrls;

    public array $translatable = [
        'name',
        'description',
        'meta_title',
        'meta_description',
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_draft' => 'boolean',
        'sort_order' => 'integer',
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
