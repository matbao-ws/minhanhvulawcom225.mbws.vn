<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use HandlesPagination;

    public function index(Request $request)
    {
        $viewer = $request->user();

        return view('admin.activity_logs.index', [
            'logs' => $this->filtered($request)->with('user')->latest('created_at')->paginate($this->perPage(20))->withQueryString(),
            'canDelete' => $viewer->isSuperAdmin(),
            'users' => User::query()
                ->whereNotNull('role_id')
                ->when(! $viewer->isSuperAdmin(), fn ($users) => $users->whereDoesntHave('role', fn ($roles) => $roles->where('is_superadmin', true)))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'actions' => AdminActivityLog::query()->visibleTo($viewer)->distinct()->orderBy('action')->pluck('action'),
            'subjectTypes' => AdminActivityLog::query()->visibleTo($viewer)->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }

    /**
     * Delete the ticked entries. Superadmin only (route middleware).
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer'],
        ]);

        $deleted = AdminActivityLog::query()->whereIn('id', $validated['ids'])->delete();

        return $this->afterDelete($deleted);
    }

    /**
     * Delete every entry matching the filters on screen — all of them when no
     * filter is set. Superadmin only (route middleware).
     */
    public function destroyAll(Request $request)
    {
        $deleted = $this->filtered($request)->delete();

        return $this->afterDelete($deleted);
    }

    /**
     * The one query both the list and "delete all" run, so the button can
     * never remove more than the screen showed.
     */
    private function filtered(Request $request): Builder
    {
        $query = AdminActivityLog::query()->visibleTo($request->user());

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }

        return $query;
    }

    private function afterDelete(int $deleted)
    {
        return redirect()
            ->route('admin.activity-logs.index', request()->only(['user_id', 'action', 'subject_type', 'from', 'to']))
            ->with('success', $deleted > 0 ? 'Đã xóa '.$deleted.' bản ghi nhật ký.' : 'Không có bản ghi nào được xóa.');
    }
}
