<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ProductOptionGroup extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = [
        'product_id',
        'name',
        'code',
        'display_type',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductOptionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
