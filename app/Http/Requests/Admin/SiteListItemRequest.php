<?php

namespace App\Http\Requests\Admin;

use App\Services\SiteListService;
use Illuminate\Foundation\Http\FormRequest;

class SiteListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware owns authorization for admin routes.
        return true;
    }

    public function rules(): array
    {
        return [
            // Same bounded shape as an editable region key: these are authored in
            // Blade, so anything else is a client inventing structure.
            'key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
            'item' => ['nullable', 'string', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/'],
            // The ids the template ships with, so a region nobody has edited grows
            // from its designed length instead of restarting at one.
            'defaults' => ['nullable', 'array', 'max:'.SiteListService::MAX_ITEMS],
            'defaults.*' => ['string', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'Mã vùng nội dung không hợp lệ.',
            'item.regex' => 'Mã mục không hợp lệ.',
        ];
    }
}
