<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Http\Requests\Concerns\NormalizesLocalizedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductVariantRequest extends FormRequest
{
    use NormalizesLocalizedInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variantId = $this->route('variant')?->id;

        return [
            ...$this->localizedStringRules('name', false, 255),
            'sku' => ['required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'barcode' => ['nullable', 'string', 'max:255'],
            'option_value_ids' => ['required', 'array', 'min:1', 'max:8'],
            'option_value_ids.*' => ['required', 'integer', 'distinct', 'exists:product_option_values,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeLocalizedInput(['name']);
    }
}
