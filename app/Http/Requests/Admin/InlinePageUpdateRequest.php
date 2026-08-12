<?php

namespace App\Http\Requests\Admin;

use App\Services\LanguageRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InlinePageUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content_locale' => ['required', 'string', Rule::in(app(LanguageRegistry::class)->codes())],
            'published_html' => ['required', 'string', 'max:1000000'],
        ];
    }
}
