<?php

namespace App\Http\Requests\Admin;

use App\Services\MenuService;
use App\Support\SectionRegistry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SiteSectionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware owns authorization for admin routes.
        return true;
    }

    public function rules(): array
    {
        return [
            // The list the section belongs to, authored in a template: lowercase,
            // dot separated. Same shape as a region key.
            'list_key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*(?:\.[a-z0-9]+(?:[_-][a-z0-9]+)*)+$/'],
            // The Blade partial name. Hyphens are fine — `product-grid` is a normal
            // partial — but never a dot: a dot separates one list's storage from the
            // next, and a name carrying one could address another section's row.
            'section' => ['required', 'string', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/'],
            /*
             * Null is the meaningful value throughout this payload: it means "no
             * override, let the template decide". So unlike SiteBlockUpdateRequest —
             * where an empty string means "hide this region" and has to be restored
             * after ConvertEmptyStringsToNull mangles it — the middleware's "" → null
             * is exactly the semantics wanted here, and undoing it would invent a
             * choice the editor never made.
             */
            'variant' => ['nullable', 'string', 'max:40'],
            'settings' => ['nullable', 'array', 'max:20'],
            'settings.*' => ['nullable', 'string', 'max:40'],
            'is_visible' => ['required', 'boolean'],
        ];
    }

    /**
     * Check the values against the registry as well.
     *
     * The service checks them too, because it also serves callers that never went
     * through HTTP. Doing it here as well is what turns "invalid" into a 422
     * naming the offending field, the same two-layer treatment
     * {@see MenuService} gives a self-entered URL.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $registry = app(SectionRegistry::class);
            $section = (string) $this->input('section');

            if (! $registry->has($section)) {
                $validator->errors()->add('section', __('admin.site_editor.validation.section_no_options'));

                return;
            }

            if (! $registry->allowsVariant($section, $this->input('variant'))) {
                $validator->errors()->add('variant', __('admin.site_editor.validation.invalid_variant'));
            }

            foreach ((array) $this->input('settings', []) as $name => $value) {
                if (! is_string($name) || ! $registry->allowsSetting($section, $name)) {
                    $validator->errors()->add('settings', __('admin.site_editor.validation.unknown_setting', ['name' => (string) $name]));

                    continue;
                }

                if (! $registry->allowsValue($section, $name, $value)) {
                    $validator->errors()->add('settings.'.$name, __('admin.site_editor.validation.invalid_value', ['name' => $name]));
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'list_key.regex' => __('admin.site_editor.validation.invalid_key'),
            'section.regex' => __('admin.site_editor.validation.invalid_section'),
        ];
    }
}
