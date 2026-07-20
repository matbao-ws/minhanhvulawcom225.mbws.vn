<?php

namespace App\Http\Requests\Admin\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ProductOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'groups' => ['required', 'array', 'min:1', 'max:8'],
            'groups.*.id' => ['nullable', 'integer'],
            'groups.*.name' => ['required', 'string', 'max:100'],
            'groups.*.display_type' => ['required', 'in:select,color,image'],
            'groups.*.values' => ['required', 'array', 'min:1', 'max:50'],
            'groups.*.values.*.id' => ['nullable', 'integer'],
            'groups.*.values.*.label' => ['required', 'string', 'max:100'],
            'groups.*.values.*.color_hex' => ['nullable', 'string', 'max:20'],
            'groups.*.values.*.image_url' => ['nullable', 'url', 'max:2048'],
            'groups.*.values.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
