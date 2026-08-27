<?php

namespace App\Http\Requests\Admin;

use App\Services\SiteListService;
use Illuminate\Foundation\Http\FormRequest;

class SiteListReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware owns authorization for admin routes.
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
            // The complete new ordering. The service rejects anything that is not
            // a permutation of what the list currently holds, so a stale tab
            // cannot drop an item by omitting it.
            'order' => ['required', 'array', 'min:1', 'max:'.SiteListService::MAX_ITEMS],
            'order.*' => ['string', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/'],
            'defaults' => ['nullable', 'array', 'max:'.SiteListService::MAX_ITEMS],
            'defaults.*' => ['string', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'Mã vùng nội dung không hợp lệ.',
            'order.required' => 'Thiếu thứ tự mới của danh sách.',
        ];
    }
}
