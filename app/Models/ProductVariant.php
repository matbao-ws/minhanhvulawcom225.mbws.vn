<?php

namespace App\Models;

use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ProductVariant extends Model
{
    use HasTranslations, HasMediaUrls;

    public array $translatable = [
        'name',
    ];

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'option_signature',
        'price',
        'compare_at_price',
        'cost_price',
        'image_url',
        'weight_grams',
        'stock_quantity',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight_grams' => 'integer',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function optionValues()
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_variant_option_values')->withTimestamps();
    }

    public static function signatureForOptionValueIds(array $optionValueIds): string
    {
        $ids = collect($optionValueIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();

        return hash('sha256', $ids->implode(','));
    }
}
