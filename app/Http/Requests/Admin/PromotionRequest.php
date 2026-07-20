<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'kind' => ['required', 'in:automatic,flash_sale'],
            'applies_to' => ['required', 'in:all_products,selected'],
            'discount_type' => ['required', 'in:percentage,fixed_amount,fixed_price'],
            'value' => [
                'required', 'numeric', 'min:0',
                function ($attribute, $value, $fail) {
                    if ($this->input('discount_type') === 'percentage' && $value > 100) {
                        $fail('Giảm theo phần trăm không được lớn hơn 100%.');
                    }
                },
            ],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'quantity_limit' => ['nullable', 'integer', 'min:1'],
            'target_quantity_limit' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_stackable' => ['nullable', 'boolean'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['nullable', 'boolean'],
            'target_product_ids' => ['nullable', 'array'],
            'target_product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'target_variant_ids' => ['nullable', 'array'],
            'target_variant_ids.*' => ['integer', 'distinct', 'exists:product_variants,id'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->input('applies_to') === 'selected'
                && empty($this->input('target_product_ids'))
                && empty($this->input('target_variant_ids'))) {
                $validator->errors()->add('target_product_ids', 'Hãy chọn ít nhất một sản phẩm hoặc SKU áp dụng.');
            }
        }];
    }
}
