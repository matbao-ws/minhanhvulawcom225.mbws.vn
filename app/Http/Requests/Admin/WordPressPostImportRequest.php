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
            'duplicate_action' => ['required', 'in:skip,update'],
        ];
    }

    public function messages(): array
    {
        return [
            'import_file.required' => 'Vui lòng chọn file XML xuất từ WordPress.',
            'import_file.extensions' => 'File import phải có định dạng XML.',
            'import_file.max' => 'File XML không được lớn hơn 20MB.',
            'duplicate_action.required' => 'Vui lòng chọn cách xử lý bài viết trùng Slug.',
        ];
    }
}
