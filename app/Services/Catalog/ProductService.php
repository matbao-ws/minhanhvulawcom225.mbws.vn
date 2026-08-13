<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Support\HtmlSanitizer;
use App\Services\LanguageRegistry;
use App\Services\LocalizedSlugService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly LanguageRegistry $languages,
    ) {
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->create($this->payload($data));
            $this->localizedSlugs->sync($product, $this->localizedValues($data['slug'] ?? []), $product->getTranslations('name'));

            return $product->refresh();
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update($this->payload($data, $product));
            $this->localizedSlugs->sync($product, $this->localizedValues($data['slug'] ?? []), $product->getTranslations('name'));

            return $product->refresh();
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(fn () => $product->delete());
    }

    public function reorder(array $ids, int $startOrder = 0): void
    {
        foreach (array_values($ids) as $index => $id) {
            Product::query()
                ->whereKey($id)
                ->update(['sort_order' => $startOrder + $index]);
        }
    }

    public function createVariant(Product $product, array $data): ProductVariant
    {
        return DB::transaction(function () use ($product, $data) {
            [$values, $signature] = $this->selectedOptionValues($product, $data['option_value_ids'] ?? []);
            $this->ensureCombinationIsAvailable($product, $signature);

            $variant = $product->variants()->create($this->variantPayload($data, null, $values, $signature));
            $variant->optionValues()->sync($values->pluck('id')->all());
            $this->syncDefaultVariant($product, $variant, (bool) ($data['is_default'] ?? false));

            return $variant->refresh();
        });
    }

    public function updateVariant(ProductVariant $variant, array $data): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data) {
            $variant->loadMissing('product');
            [$values, $signature] = $this->selectedOptionValues($variant->product, $data['option_value_ids'] ?? []);
            $this->ensureCombinationIsAvailable($variant->product, $signature, $variant->id);

            $variant->update($this->variantPayload($data, $variant, $values, $signature));
            $variant->optionValues()->sync($values->pluck('id')->all());
            $this->syncDefaultVariant($variant->product, $variant, (bool) ($data['is_default'] ?? false));

            return $variant->refresh();
        });
    }

    /** Create every missing combination from the active option values. */
    public function generateVariants(Product $product): int
    {
        return DB::transaction(function () use ($product) {
            $product->load('optionGroups.values');
            $groups = $product->optionGroups;

            if ($groups->isEmpty()) {
                throw new DomainException('Hãy khai báo ít nhất một nhóm thuộc tính trước khi tạo tổ hợp SKU.');
            }

            $valuesByGroup = $groups->map(function ($group) {
                $values = $group->values->where('is_active', true)->values();
                if ($values->isEmpty()) {
                    throw new DomainException("Nhóm thuộc tính {$group->name} chưa có giá trị đang hoạt động.");
                }

                return $values;
            });

            $combinationCount = $valuesByGroup->reduce(fn (int $total, $values) => $total * $values->count(), 1);
            if ($combinationCount > 300) {
                throw new DomainException('Có quá nhiều tổ hợp (tối đa 300 SKU trong một lần tạo). Hãy thu hẹp các giá trị thuộc tính.');
            }

            $created = 0;
            foreach ($this->cartesian($valuesByGroup->all()) as $values) {
                $values = collect($values);
                $signature = ProductVariant::signatureForOptionValueIds($values->pluck('id')->all());
                if ($product->variants()->where('option_signature', $signature)->exists()) {
                    continue;
                }

                $variant = $product->variants()->create([
                    'name' => $this->variantName($values),
                    'sku' => $this->generatedSku($product, $values),
                    'option_signature' => $signature,
                    'stock_quantity' => 0,
                    'is_active' => true,
                    'is_default' => ! $product->variants()->where('is_default', true)->exists(),
                    'sort_order' => (int) $product->variants()->max('sort_order') + 1,
                ]);
                $variant->optionValues()->sync($values->pluck('id')->all());
                $created++;
            }

            return $created;
        });
    }

    public function deleteVariant(ProductVariant $variant): void
    {
        DB::transaction(fn () => $variant->delete());
    }

    private function payload(array $data, ?Product $product = null): array
    {
        $name = $this->translationValue($data['name'] ?? null, $product, 'name');
        $submittedSlugs = $this->localizedValues($data['slug'] ?? []);
        $baseSlug = $submittedSlugs[$this->languages->defaultLocale()] ?? $submittedSlugs[app()->getLocale()] ?? ($name[$this->languages->defaultLocale()] ?? $name[$this->fallbackLocale()] ?? reset($name));

        $categoryId = $data['category_id'] ?? null;
        if (empty($categoryId)) {
            $defaultCategory = \App\Models\Category::query()->firstOrCreate(
                ['slug' => 'chua-phan-loai'],
                [
                    'name' => ['vi' => 'Chưa phân loại', 'en' => 'Uncategorized'],
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
            'meta_title' => $this->translationValue($data['meta_title'] ?? null, $product, 'meta_title'),
            'meta_description' => $this->translationValue($data['meta_description'] ?? null, $product, 'meta_description'),
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

    private function variantPayload(array $data, ?ProductVariant $variant, $optionValues, string $signature): array
    {
        $name = $data['name'] ?? $this->variantName($optionValues);

        return [
            'name' => $this->translationValue($name, $variant, 'name'),
            'sku' => $data['sku'],
            'barcode' => $data['barcode'] ?? null,
            'option_signature' => $signature,
            'price' => $data['price'] ?? null,
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'cost_price' => $data['cost_price'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'weight_grams' => $data['weight_grams'] ?? null,
            'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function selectedOptionValues(Product $product, array $optionValueIds): array
    {
        $ids = collect($optionValueIds)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $groups = $product->optionGroups()->with('values')->get();

        if ($groups->isEmpty()) {
            throw new DomainException('Sản phẩm chưa có nhóm thuộc tính. Hãy cấu hình thuộc tính trước khi tạo SKU.');
        }

        if ($ids->count() !== $groups->count()) {
            throw new DomainException('Mỗi nhóm thuộc tính phải chọn đúng một giá trị.');
        }

        $values = ProductOptionValue::query()->with('optionGroup')->whereIn('id', $ids)->get()->keyBy('id');
        if ($values->count() !== $ids->count()) {
            throw new DomainException('Có giá trị thuộc tính không tồn tại.');
        }

        foreach ($groups as $group) {
            $selected = $values->filter(fn (ProductOptionValue $value) => $value->product_option_group_id === $group->id);
            if ($selected->count() !== 1 || ! $selected->first()->is_active) {
                throw new DomainException("Nhóm thuộc tính {$group->name} phải chọn một giá trị đang hoạt động.");
            }
        }

        return [$values->sortBy('id')->values(), ProductVariant::signatureForOptionValueIds($ids->all())];
    }

    private function ensureCombinationIsAvailable(Product $product, string $signature, ?int $ignoreVariantId = null): void
    {
        $exists = $product->variants()->where('option_signature', $signature)
            ->when($ignoreVariantId, fn ($query) => $query->whereKeyNot($ignoreVariantId))
            ->exists();

        if ($exists) {
            throw new DomainException('Tổ hợp thuộc tính này đã có SKU.');
        }
    }

    private function syncDefaultVariant(Product $product, ProductVariant $variant, bool $isDefault): void
    {
        if ($isDefault) {
            $product->variants()->whereKeyNot($variant->id)->update(['is_default' => false]);
        }
    }

    private function cartesian(array $sets, array $prefix = []): array
    {
        if ($sets === []) {
            return [$prefix];
        }

        $first = array_shift($sets);
        $combinations = [];
        foreach ($first as $value) {
            $combinations = [...$combinations, ...$this->cartesian($sets, [...$prefix, $value])];
        }

        return $combinations;
    }

    private function generatedSku(Product $product, $optionValues): string
    {
        $base = Str::upper(Str::ascii($product->sku ?: 'PRODUCT-'.$product->id));
        $suffix = $optionValues->map(fn (ProductOptionValue $value) => Str::upper(Str::ascii($value->code)))->implode('-');
        $candidate = substr(trim($base.'-'.$suffix, '-'), 0, 240) ?: 'SKU-'.Str::upper(Str::random(8));
        $sku = $candidate;
        $counter = 2;

        while (ProductVariant::query()->where('sku', $sku)->exists()) {
            $sku = substr($candidate, 0, 240).'-'.$counter++;
        }

        return $sku;
    }

    private function variantName($optionValues): string
    {
        return $optionValues
            ->map(fn (ProductOptionValue $value) => $value->getTranslation('label', app()->getLocale(), false)
                ?: $value->getTranslation('label', $this->fallbackLocale(), false)
                ?: $value->label)
            ->implode(' / ');
    }

    private function translationValue(string|array|null $value, Product|ProductVariant|null $model, string $attribute): array
    {
        $translations = $model?->getTranslations($attribute) ?? [];
        $locale = app()->getLocale() ?: $this->fallbackLocale();
        $fallbackLocale = $this->fallbackLocale();

        if (is_array($value)) {
            foreach ($value as $lang => $val) {
                if (is_string($val) && trim($val) !== '') {
                    $cleanValue = trim($val);
                    $translations[$lang] = in_array($attribute, ['short_description', 'description'], true)
                        ? $this->htmlSanitizer->clean($cleanValue)
                        : $cleanValue;
                }
            }
        } else {
        $value = is_string($value) ? trim($value) : '';
        if (in_array($attribute, ['short_description', 'description'], true)) {
            $value = $this->htmlSanitizer->clean($value);
        }
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
        return $this->languages->fallbackLocale();
    }

    /** @return array<string, string> */
    private function localizedValues(string|array|null $value): array
    {
        if (is_array($value)) {
            return collect($value)->filter(fn ($item, $locale) => $this->languages->supports((string) $locale) && is_string($item) && trim($item) !== '')
                ->map(fn (string $item) => trim($item))->all();
        }

        return is_string($value) && trim($value) !== '' ? [app()->getLocale() => trim($value)] : [];
    }

    private function uniqueProductSlug(string $value, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value) ?: Str::random(8);
        $base = $slug;
        $counter = 2;
        while (Product::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
