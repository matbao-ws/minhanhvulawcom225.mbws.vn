<?php

namespace App\Models;

use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ProductOptionValue extends Model
{
    use HasTranslations, HasMediaUrls;

    public array $translatable = ['label'];

    protected $fillable = [
        'product_option_group_id',
        'label',
        'code',
        'color_hex',
        'image_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function optionGroup()
    {
        return $this->belongsTo(ProductOptionGroup::class, 'product_option_group_id');
    }

    public function variants()
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_option_values')->withTimestamps();
    }
}
