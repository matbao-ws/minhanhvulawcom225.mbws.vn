<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
            'avatar_file' => ['nullable', 'file', 'image', 'max:2048'], // 2MB max
            'avatar_url' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->isMethod('POST')) {
            $rules['password'] = ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()];
        } else {
            $rules['password'] = ['nullable', 'string', Password::min(12)->mixedCase()->numbers()->symbols()];
        }

        return $rules;
    }
}
