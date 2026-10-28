<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WordPressPostImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'import_file' => ['required', 'file', 'max:20480', 'extensions:xml'],
            // The route needs posts.create; overwriting existing posts needs posts.update.
            'duplicate_action' => ['required', $this->user()?->can('posts.update') ? 'in:skip,update' : 'in:skip'],
        ];
    }

    public function messages(): array
    {
        return [
            'import_file.required' => __('admin.posts.wordpress.validation.file_required'),
            'import_file.extensions' => __('admin.posts.wordpress.validation.file_extensions'),
            'import_file.max' => __('admin.posts.wordpress.validation.file_max'),
            'duplicate_action.required' => __('admin.posts.wordpress.validation.duplicate_required'),
            'duplicate_action.in' => __('admin.posts.wordpress.validation.duplicate_update_forbidden'),
        ];
    }
}
