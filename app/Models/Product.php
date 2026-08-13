<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedSlugs;
use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasLocalizedSlugs, HasTranslations, HasMediaUrls;

    public array $translatable = [
        'name',
        'short_description',
        'description',
        'meta_title',
        'meta_description',
    ];

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'meta_title',
        'meta_description',
        'image_url',
        'price',
        'compare_at_price',
        'cost_price',
        'stock_quantity',
        'manage_stock',
        'is_active',
        'is_featured',
        'sort_order',
        'published_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'manage_stock' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function optionGroups()
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function usesVariantInventory(): bool
    {
        return $this->relationLoaded('optionGroups')
            ? $this->optionGroups->isNotEmpty()
            : $this->optionGroups()->exists();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
