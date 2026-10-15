<?php

namespace App\Http\Requests\Admin;

use App\Services\LanguageRegistry;
use App\Support\Seo\SeoBox;
use Illuminate\Foundation\Http\FormRequest;

class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $languages = app(LanguageRegistry::class);
        $rules = [
            'title' => ['required', 'array'],
            'slug' => ['nullable', 'array'],
            'meta_title' => ['nullable', 'array'],
            'meta_description' => ['nullable', 'array'],
            'published_html' => ['nullable', 'array'],
            'is_active' => ['required', 'boolean'],
            ...app(SeoBox::class)->rules(),
        ];

        foreach ($languages->codes() as $locale) {
            $required = $locale === $languages->defaultLocale() ? 'required' : 'nullable';
            $rules["title.$locale"] = [$required, 'string', 'max:255'];
            $rules["slug.$locale"] = ['nullable', 'string', 'max:255'];
            $rules["meta_title.$locale"] = ['nullable', 'string', 'max:255'];
            $rules["meta_description.$locale"] = ['nullable', 'string', 'max:500'];
            $rules["published_html.$locale"] = ['nullable', 'string', 'max:500000'];
        }

        return $rules;
    }
}
