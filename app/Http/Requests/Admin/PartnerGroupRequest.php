<?php

namespace App\Http\Requests\Admin;

use App\Models\PartnerGroup;
use Illuminate\Foundation\Http\FormRequest;

class PartnerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        // The key is chosen once (or slugged from the name) and never edited: themes ask for it.
        if (! $this->route('partner_group') instanceof PartnerGroup) {
            $rules['key'] = ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:partner_groups,key'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'name' => __('partners.groups.fields.name'),
            'key' => __('partners.groups.fields.key'),
            'sort_order' => __('partners.groups.fields.sort_order'),
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => __('partners.groups.validation.key_format'),
            'key.unique' => __('partners.groups.validation.key_taken'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('key'))) {
            $this->merge(['key' => strtolower(trim($this->input('key')))]);
        }
    }
}
