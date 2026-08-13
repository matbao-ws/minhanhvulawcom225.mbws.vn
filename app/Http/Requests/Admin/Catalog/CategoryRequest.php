<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Http\Requests\Concerns\NormalizesLocalizedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    use NormalizesLocalizedInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                Rule::notIn(array_filter([$categoryId])),
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $parent = \App\Models\Category::find($value);
                        if ($parent && $parent->slug === 'chua-phan-loai') {
                            $fail(__('catalog.categories.uncategorized_cannot_have_children'));
                        }

                        $categoryId = $this->route('category')?->id;
                        while ($parent && $categoryId) {
                            if ((int) $parent->id === (int) $categoryId) {
                                $fail('Danh mục cha không thể là danh mục con của chính nó.');
                                break;
                            }
                            $parent = $parent->parent_id ? \App\Models\Category::find($parent->parent_id) : null;
                        }
                    }
                }
            ],
            ...$this->localizedStringRules('name', true, 255),
            ...$this->localizedStringRules('slug', false, 255),
            ...$this->localizedStringRules('description'),
            ...$this->localizedStringRules('meta_title', false, 255),
            ...$this->localizedStringRules('meta_description', false, 500),
            'image_url' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_draft' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeLocalizedInput(['name', 'slug', 'description', 'meta_title', 'meta_description']);
    }
}
