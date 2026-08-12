<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Http\Requests\Concerns\NormalizesLocalizedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    use NormalizesLocalizedInput;

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
            ...$this->localizedStringRules('name', true, 255),
            ...$this->localizedStringRules('slug', false, 255),
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            ...$this->localizedStringRules('short_description'),
            ...$this->localizedStringRules('description'),
            ...$this->localizedStringRules('meta_title', false, 255),
            ...$this->localizedStringRules('meta_description', false, 500),
            'image_url' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'file', 'image', 'max:5120'],
            'gallery_images' => ['nullable', 'array', 'max:20'],
            'gallery_images.*' => ['nullable', 'url', 'max:2048'],
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
            'variant_groups.*.name' => [$excludeVariantFields, 'required', 'array'],
            'variant_groups.*.display_type' => [$excludeVariantFields, 'required', 'in:select,color,image'],
            'variant_groups.*.values' => [$excludeVariantFields, 'required', 'array', 'min:1', 'max:50'],
            'variant_groups.*.values.*.id' => [$excludeVariantFields, 'nullable', 'integer'],
            'variant_groups.*.values.*.label' => [$excludeVariantFields, 'required', 'array'],
            'variant_groups.*.values.*.color_hex' => [$excludeVariantFields, 'nullable', 'string', 'max:20'],
            'variant_groups.*.values.*.image_url' => [$excludeVariantFields, 'nullable', 'url', 'max:2048'],
            'variant_groups.*.values.*.is_active' => [$excludeVariantFields, 'nullable', 'boolean'],
            ...$this->variantTranslationRules($excludeVariantFields),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeLocalizedInput(['name', 'slug', 'short_description', 'description', 'meta_title', 'meta_description']);
        $registry = app(\App\Services\LanguageRegistry::class);
        $groups = $this->input('variant_groups', []);
        foreach ($groups as &$group) {
            if (is_string($group['name'] ?? null)) $group['name'] = [$registry->defaultLocale() => $group['name']];
            if (! empty($group['values']) && is_array($group['values'])) {
                foreach ($group['values'] as &$value) {
                    if (is_string($value['label'] ?? null)) $value['label'] = [$registry->defaultLocale() => $value['label']];
                }
                unset($value);
            }
        }
        unset($group);
        if ($groups !== []) $this->merge(['variant_groups' => $groups]);
    }

    private function variantTranslationRules($excludeVariantFields): array
    {
        $rules = [];
        $registry = app(\App\Services\LanguageRegistry::class);
        foreach ($registry->codes() as $locale) {
            $required = $locale === $registry->defaultLocale() ? 'required' : 'nullable';
            $rules["variant_groups.*.name.$locale"] = [$excludeVariantFields, $required, 'string', 'max:100'];
            $rules["variant_groups.*.values.*.label.$locale"] = [$excludeVariantFields, $required, 'string', 'max:100'];
        }
        return $rules;
    }
}
