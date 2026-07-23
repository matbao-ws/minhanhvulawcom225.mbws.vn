<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Services\LanguageRegistry;
use Illuminate\Foundation\Http\FormRequest;

class ProductOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'groups' => ['required', 'array', 'min:1', 'max:8'],
            'groups.*.id' => ['nullable', 'integer'],
            'groups.*.name' => ['required', 'array'],
            'groups.*.display_type' => ['required', 'in:select,color,image'],
            'groups.*.values' => ['required', 'array', 'min:1', 'max:50'],
            'groups.*.values.*.id' => ['nullable', 'integer'],
            'groups.*.values.*.label' => ['required', 'array'],
            'groups.*.values.*.color_hex' => ['nullable', 'string', 'max:20'],
            'groups.*.values.*.image_url' => ['nullable', 'url', 'max:2048'],
            'groups.*.values.*.is_active' => ['nullable', 'boolean'],
        ];

        $languages = app(LanguageRegistry::class);
        foreach ($languages->codes() as $locale) {
            $required = $locale === $languages->defaultLocale() ? 'required' : 'nullable';
            $rules["groups.*.name.$locale"] = [$required, 'string', 'max:100'];
            $rules["groups.*.values.*.label.$locale"] = [$required, 'string', 'max:100'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $languages = app(LanguageRegistry::class);
        $groups = $this->input('groups', []);
        foreach ($groups as &$group) {
            if (is_string($group['name'] ?? null)) {
                $group['name'] = [$languages->defaultLocale() => $group['name']];
            }
            if (! empty($group['values']) && is_array($group['values'])) {
                foreach ($group['values'] as &$value) {
                    if (is_string($value['label'] ?? null)) {
                        $value['label'] = [$languages->defaultLocale() => $value['label']];
                    }
                }
                unset($value);
            }
        }
        unset($group);
        $this->merge(['groups' => $groups]);
    }
}
