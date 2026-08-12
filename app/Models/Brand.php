<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedSlugs;
use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Brand extends Model
{
    use HasLocalizedSlugs, HasTranslations, HasMediaUrls;

    public array $translatable = [
        'name',
        'description',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
