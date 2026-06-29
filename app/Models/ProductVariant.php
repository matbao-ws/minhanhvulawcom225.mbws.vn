<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ProductVariant extends Model
{
    use HasTranslations;

    public array $translatable = [
        'name',
    ];

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'option_values',
        'price',
        'stock_quantity',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'option_values' => 'array',
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
