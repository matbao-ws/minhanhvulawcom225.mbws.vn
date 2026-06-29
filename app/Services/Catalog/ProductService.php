<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductService
{
    public function create(array $data): Product
    {
        $limit = app(FeatureGate::class)->limit('max_products');

        if ($limit !== null && Product::query()->count() >= $limit) {
            throw new RuntimeException(__('catalog.products.limit_reached', ['limit' => $limit]));
        }

        return DB::transaction(fn () => Product::query()->create($this->payload($data)));
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update($this->payload($data, $product));

            return $product->refresh();
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(fn () => $product->delete());
    }

    public function createVariant(Product $product, array $data): ProductVariant
    {
        return DB::transaction(fn () => $product->variants()->create($this->variantPayload($data)));
    }

    public function updateVariant(ProductVariant $variant, array $data): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data) {
            $variant->update($this->variantPayload($data, $variant));

            return $variant->refresh();
        });
    }

    public function deleteVariant(ProductVariant $variant): void
    {
        DB::transaction(fn () => $variant->delete());
    }

    private function payload(array $data, ?Product $product = null): array
    {
        $name = $this->translationValue($data['name'] ?? null, $product, 'name');
        $baseSlug = ($data['slug'] ?? null) ?: ($name[app()->getLocale()] ?? $name[$this->fallbackLocale()] ?? reset($name));

        $categoryId = $data['category_id'] ?? null;
        if (empty($categoryId)) {
            $defaultCategory = \App\Models\Category::query()->firstOrCreate(
                ['slug' => 'chua-phan-loai'],
                [
                    'name' => [
                        'vi' => 'Chưa phân loại',
                        'en' => 'Uncategorized',
                    ],
                    'description' => [
                        'vi' => 'Danh mục mặc định cho các sản phẩm chưa được phân loại.',
                        'en' => 'Default category for uncategorized products.',
                    ],
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );
            $categoryId = $defaultCategory->id;
        }

        return [
            'category_id' => $categoryId,
            'brand_id' => $data['brand_id'] ?? null,
            'name' => $name,
            'slug' => $this->uniqueProductSlug((string) $baseSlug, $product?->id),
            'sku' => $data['sku'] ?? null,
            'short_description' => $this->translationValue($data['short_description'] ?? null, $product, 'short_description'),
            'description' => $this->translationValue($data['description'] ?? null, $product, 'description'),
            'image_url' => $data['image_url'] ?? null,
            'price' => $data['price'] ?? 0,
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'cost_price' => $data['cost_price'] ?? null,
            'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            'manage_stock' => (bool) ($data['manage_stock'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'published_at' => $data['published_at'] ?? null,
        ];
    }

    private function variantPayload(array $data, ?ProductVariant $variant = null): array
    {
        return [
            'name' => $this->translationValue($data['name'] ?? null, $variant, 'name'),
            'sku' => $data['sku'],
            'option_values' => $this->optionValues($data),
            'price' => $data['price'] ?? null,
            'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function translationValue(string|array|null $value, Product|ProductVariant|null $model, string $attribute): array
    {
        $translations = $model?->getTranslations($attribute) ?? [];
        $locale = app()->getLocale() ?: $this->fallbackLocale();
        $fallbackLocale = $this->fallbackLocale();

        if (is_array($value)) {
            foreach ($value as $lang => $val) {
                if (is_string($val) && trim($val) !== '') {
                    $translations[$lang] = trim($val);
                }
            }
        } else {
            $value = is_string($value) ? trim($value) : '';

            if ($value !== '') {
                $translations[$locale] = $value;
            }

            if ($locale !== $fallbackLocale && $value !== '' && empty($translations[$fallbackLocale])) {
                $translations[$fallbackLocale] = $value;
            }
        }

        return array_filter($translations, fn ($translation) => $translation !== null && $translation !== '');
    }

    private function fallbackLocale(): string
    {
        return config('app.fallback_locale', config('app.locale', 'en'));
    }

    private function optionValues(array $data): array
    {
        $names = $data['option_names'] ?? [];
        $values = $data['option_values'] ?? [];
        $options = [];

        foreach ($names as $index => $name) {
            $name = is_string($name) ? trim($name) : '';
            $value = isset($values[$index]) && is_string($values[$index]) ? trim($values[$index]) : '';

            if ($name !== '' && $value !== '') {
                $options[$name] = $value;
            }
        }

        return $options;
    }

    private function uniqueProductSlug(string $value, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value) ?: Str::random(8);
        $base = $slug;
        $counter = 2;

        while (Product::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
