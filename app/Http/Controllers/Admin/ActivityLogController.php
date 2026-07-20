<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AdminActivityLog::query()->with('user')->latest('created_at');

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

        return view('admin.activity_logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'users' => User::query()->whereNotNull('role_id')->orderBy('name')->get(['id', 'name', 'email']),
            'actions' => AdminActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'subjectTypes' => AdminActivityLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
