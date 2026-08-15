<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware owns authorization for admin screens.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('key')) {
            $this->merge(['key' => \Illuminate\Support\Str::slug((string) $this->input('key'))]);
        }
    }

    public function rules(): array
    {
        $menu = $this->route('menu');

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('menus', 'key')->ignore($menu?->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => __('admin.menus.validation.key_format'),
            'key.unique' => __('admin.menus.validation.key_unique'),
        ];
    }
}
