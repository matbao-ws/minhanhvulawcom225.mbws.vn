<?php

namespace App\Http\Requests\Admin\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        $excludeVariantFields = Rule::excludeIf(! $this->boolean('has_variants'));

        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'file', 'image', 'max:5120'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'manage_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'has_variants' => ['nullable', 'boolean'],
            'variant_groups' => [$excludeVariantFields, 'required', 'array', 'min:1', 'max:8'],
            'variant_groups.*.id' => [$excludeVariantFields, 'nullable', 'integer'],
            'variant_groups.*.name' => [$excludeVariantFields, 'required', 'string', 'max:100'],
            'variant_groups.*.display_type' => [$excludeVariantFields, 'required', 'in:select,color,image'],
            'variant_groups.*.values' => [$excludeVariantFields, 'required', 'array', 'min:1', 'max:50'],
            'variant_groups.*.values.*.id' => [$excludeVariantFields, 'nullable', 'integer'],
            'variant_groups.*.values.*.label' => [$excludeVariantFields, 'required', 'string', 'max:100'],
            'variant_groups.*.values.*.color_hex' => [$excludeVariantFields, 'nullable', 'string', 'max:20'],
            'variant_groups.*.values.*.image_url' => [$excludeVariantFields, 'nullable', 'url', 'max:2048'],
            'variant_groups.*.values.*.is_active' => [$excludeVariantFields, 'nullable', 'boolean'],
        ];
    }
}
