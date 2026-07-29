<?php

namespace App\Http\Requests\Admin;

use App\Services\LanguageRegistry;
use Illuminate\Foundation\Http\FormRequest;

class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['builder_data', 'published_html', 'published_css'] as $field) {
            if (is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
    }

    public function rules(): array
    {
        $languages = app(LanguageRegistry::class);
        $rules = [
            'title' => ['required', 'array'],
            'slug' => ['nullable', 'array'],
            'meta_title' => ['nullable', 'array'],
            'meta_description' => ['nullable', 'array'],
            'builder_data' => ['required', 'array'],
            'builder_data.version' => ['required', 'integer', 'min:1', 'max:10'],
            'builder_data.locales' => ['required', 'array'],
            'published_html' => ['nullable', 'array'],
            'published_css' => ['nullable', 'array'],
            'is_active' => ['required', 'boolean'],
        ];

        foreach ($languages->codes() as $locale) {
            $required = $locale === $languages->defaultLocale() ? 'required' : 'nullable';
            $rules["title.$locale"] = [$required, 'string', 'max:255'];
            $rules["slug.$locale"] = ['nullable', 'string', 'max:255'];
            $rules["meta_title.$locale"] = ['nullable', 'string', 'max:255'];
            $rules["meta_description.$locale"] = ['nullable', 'string', 'max:500'];
            $rules["builder_data.locales.$locale"] = ['nullable', 'array'];
            $rules["published_html.$locale"] = ['nullable', 'string', 'max:1000000'];
            $rules["published_css.$locale"] = ['nullable', 'string', 'max:500000'];
        }

        return $rules;
    }
}
