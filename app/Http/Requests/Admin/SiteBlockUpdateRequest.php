<?php

namespace App\Http\Requests\Admin;

use App\Models\SiteBlock;
use App\Services\LanguageRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteBlockUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware owns authorization for admin routes.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // ConvertEmptyStringsToNull turns "" into null before validation, and an
        // empty string is the value that means "hide this region". Put it back.
        $this->merge(['value' => (string) $this->input('value')]);
    }

    public function rules(): array
    {
        return [
            // Keys are authored in templates: lowercase, dot separated.
            'key' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*(?:\.[a-z0-9]+(?:[_-][a-z0-9]+)*)+$/'],
            'type' => ['required', Rule::in(SiteBlock::TYPES)],
            // The heading level chosen from the inline toolbar. Null keeps the tag
            // the Blade template authored, which is what an untouched region uses.
            'format' => ['nullable', 'string', Rule::in(SiteBlock::FORMATS)],
            'content_locale' => ['required', 'string', Rule::in(app(LanguageRegistry::class)->codes())],
            // An empty string is a real value: it hides the region.
            'value' => ['present', 'string', 'max:200000'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'Mã vùng nội dung không hợp lệ.',
        ];
    }
}
