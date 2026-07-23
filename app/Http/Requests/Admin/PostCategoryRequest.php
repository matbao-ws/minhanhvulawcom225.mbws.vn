<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesLocalizedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostCategoryRequest extends FormRequest
{
    use NormalizesLocalizedInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeLocalizedInput(['name', 'slug', 'description']);
    }

    public function rules(): array
    {
        $category = $this->route('post_category');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:post_categories,id', Rule::notIn(array_filter([$category?->id]))],
            ...$this->localizedStringRules('name', true, 255),
            ...$this->localizedStringRules('slug', false, 255),
            ...$this->localizedStringRules('description'),
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
