<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait HandlesBulkActions
{
    /**
     * @param  string  $module  Permission module owning these records, e.g. `products`.
     * @return array{ids: array<int, int>, action: 'activate'|'deactivate'|'delete'}
     */
    protected function validatedBulkAction(Request $request, string $table, string $module): array
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

        // The route only demands `<module>.update`, because activate and
        // deactivate are edits. Deleting through the same endpoint must still
        // cost the delete permission.
        if ($validated['action'] === 'delete') {
            abort_unless($request->user()?->can("{$module}.delete"), 403, 'Unauthorized.');
        }

        return $validated;
    }
}
