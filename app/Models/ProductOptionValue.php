<?php

namespace App\Models;

use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ProductOptionValue extends Model
{
    use HasMediaUrls, HasTranslations;

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

    /** @return BelongsTo<ProductOptionGroup, $this> */
    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'product_option_group_id');
    }

    public function variants()
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_option_values')->withTimestamps();
    }
}
