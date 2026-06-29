<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function credentials(): array
    {
        return $this->only('email', 'password');
    }

    public function remember(): bool
    {
        return $this->boolean('remember');
    }
}
