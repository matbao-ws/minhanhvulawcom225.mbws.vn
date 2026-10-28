<?php

namespace App\Http\Requests\Admin;

use App\Models\SiteBlock;
use App\Services\LanguageRegistry;
use App\Services\SiteListService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Copy one box of a repeatable region: the box, and what its regions show right now (saved or the
 * template's default) in the language being edited. Each value is saved through the same path as a
 * region edit, so it is sanitized the same way.
 */
class SiteListDuplicateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware owns authorization for admin routes.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // An empty string is a real value (a hidden region); ConvertEmptyStringsToNull must not turn it into null.
        $slots = $this->input('slots');
        if (is_array($slots)) {
            $this->merge(['slots' => array_map(fn ($slot) => is_array($slot) ? ['value' => (string) ($slot['value'] ?? '')] + $slot : $slot, $slots)]);
        }
    }

    public function rules(): array
    {
        $id = 'regex:/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/';

        return [
            'key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
            // Absent: a copy of the authored region itself, placed first.
            'item' => ['nullable', 'string', $id],
            'defaults' => ['nullable', 'array', 'max:'.SiteListService::MAX_ITEMS],
            'defaults.*' => ['string', $id],
            'content_locale' => ['required', 'string', Rule::in(app(LanguageRegistry::class)->codes())],
            'slots' => ['present', 'array', 'max:40'],
            // The part of a region key after "<list>.item_<id>.", e.g. "name" or "card.title".
            'slots.*.slot' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*(?:\.[a-z0-9]+(?:[_-][a-z0-9]+)*)*$/'],
            'slots.*.type' => ['required', Rule::in(SiteBlock::TYPES)],
            'slots.*.value' => ['present', 'string', 'max:200000'],
            'slots.*.format' => ['nullable', 'string', Rule::in(SiteBlock::FORMATS)],
            'slots.*.align' => ['nullable', 'string', Rule::in(SiteBlock::ALIGNS)],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => __('admin.site_editor.validation.invalid_key'),
            'item.regex' => __('admin.site_editor.validation.invalid_item'),
        ];
    }
}
