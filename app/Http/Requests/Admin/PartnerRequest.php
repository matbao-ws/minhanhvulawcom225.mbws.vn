<?php

namespace App\Http\Requests\Admin;

use App\Models\Partner;
use App\Support\ClientBackground;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Partner|null $partner */
        $partner = $this->route('partner');
        $creating = ! $partner instanceof Partner;

        return [
            'partner_group_id' => ['required', 'integer', 'exists:partner_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            // A logo is required on create; on edit, leaving both empty keeps the current one.
            'image_url' => [...($creating ? ['required_without:image_file'] : []), 'nullable', 'string', 'max:2048'],
            'image_file' => ['nullable', 'image', 'max:2048'],
            'url' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'partner_group_id' => __('partners.fields.group'),
            'name' => __('partners.fields.name'),
            'image_url' => __('partners.fields.logo'),
            'image_file' => __('partners.fields.logo'),
            'url' => __('partners.fields.url'),
        ];
    }

    public function messages(): array
    {
        return [
            'image_url.required_without' => __('partners.validation.logo_required'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $logo = trim((string) $this->input('image_url'));
            // The picture lands in an <img src>: a site path or an http(s) URL, nothing else.
            if ($logo !== '' && ! ClientBackground::isSafeUrl($logo)) {
                $validator->errors()->add('image_url', __('partners.validation.logo_url'));
            }

            // The link lands in an href: a same-site path or an http(s) link only.
            $url = trim((string) $this->input('url'));
            if ($url !== '') {
                $isRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
                if (! $isRelative && ! in_array($scheme, ['http', 'https'], true)) {
                    $validator->errors()->add('url', __('partners.validation.url_scheme'));
                }
            }
        });
    }
}
