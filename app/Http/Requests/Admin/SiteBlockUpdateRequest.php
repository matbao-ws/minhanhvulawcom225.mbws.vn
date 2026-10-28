<?php

namespace App\Http\Requests\Admin;

use App\Models\SiteBlock;
use App\Services\LanguageRegistry;
use App\Support\ClientBackground;
use Closure;
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
        $rules = [
            // Keys are authored in templates: lowercase, dot separated.
            'key' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*(?:\.[a-z0-9]+(?:[_-][a-z0-9]+)*)+$/'],
            'type' => ['required', Rule::in(SiteBlock::TYPES)],
            // The heading level chosen from the inline toolbar. Null keeps the tag
            // the Blade template authored, which is what an untouched region uses.
            'format' => ['nullable', 'string', Rule::in(SiteBlock::FORMATS)],
            // Alignment token from the inline toolbar. Absent leaves it as it is; null returns to the theme's.
            'align' => ['sometimes', 'nullable', 'string', Rule::in(SiteBlock::ALIGNS)],
            'content_locale' => ['required', 'string', Rule::in(app(LanguageRegistry::class)->codes())],
            // An empty string is a real value: it hides the region.
            'value' => ['present', 'string', 'max:200000'],
        ];

        if (in_array($this->input('type'), SiteBlock::PICTURE_TYPES, true)) {
            // A picture goes into src or a CSS url(): a site path or an http(s) URL, nothing that
            // could close the attribute, the url() or the declaration (no javascript:, data:, quotes).
            $rules['value'] = ['present', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== '' && ! ClientBackground::isSafeUrl((string) $value)) {
                    $fail(__('admin.site_editor.validation.invalid_image'));
                }
            }];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'key.regex' => __('admin.site_editor.validation.invalid_key'),
        ];
    }
}
