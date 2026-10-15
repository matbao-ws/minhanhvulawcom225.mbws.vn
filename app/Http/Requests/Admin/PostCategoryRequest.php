<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesLocalizedInput;
use App\Support\Seo\SeoBox;
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
        $this->normalizeLocalizedInput(['name', 'slug', 'description', 'meta_title', 'meta_description']);
    }

    public function rules(): array
    {
        $category = $this->route('post_category');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:post_categories,id', Rule::notIn(array_filter([$category?->id]))],
            ...$this->localizedStringRules('name', true, 255),
            ...$this->localizedStringRules('slug', false, 255),
            ...$this->localizedStringRules('description'),
            ...$this->localizedStringRules('meta_title', false, 255),
            ...$this->localizedStringRules('meta_description', false, 500),
            ...app(SeoBox::class)->rules(),
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
