<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait HandlesBulkActions
{
    /**
     * @return array{ids: array<int, int>, action: 'activate'|'deactivate'|'delete'}
     */
    protected function validatedBulkAction(Request $request, string $table): array
    {
        /** @var array{ids: array<int, int>, action: 'activate'|'deactivate'|'delete'} $validated */
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists($table, 'id')],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
        ], [
            'ids.required' => 'Hãy chọn ít nhất một mục để thao tác.',
            'ids.max' => 'Mỗi lần chỉ có thể thao tác tối đa 100 mục.',
        ]);

        return $validated;
    }
}
