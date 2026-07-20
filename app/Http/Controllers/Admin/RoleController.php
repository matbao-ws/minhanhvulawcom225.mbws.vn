<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    private function permissionsByGroup()
    {
        return Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group');
    }

    private function permissionRule(): string
    {
        return 'string|in:'.Permission::query()->pluck('code')->implode(',');
    }

    /**
     * Display a listing of the roles.
     */
    public function index()
    {
        $query = Role::query()->withCount('users');
        if (! auth()->user()->isSuperAdmin()) {
            $query->where('is_system', false);
        }
        $roles = $query->orderBy('id')->paginate(15);

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create()
    {
        $role = new Role();
        $permissionsByGroup = $this->permissionsByGroup();

        return view('admin.roles.create', compact('role', 'permissionsByGroup'));
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => $this->permissionRule(),
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'permissions' => $validated['permissions'] ?? [],
        ]);
        ActivityLogger::log('created', $role, "Tạo vai trò {$role->name}", [
            'new' => ['name' => $role->name, 'permissions' => $role->permissions],
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã tạo vai trò mới thành công.'
            ]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Đã tạo vai trò mới thành công.');
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(string $locale, Role $role)
    {
        if ($role->is_system) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Không thể chỉnh sửa vai trò hệ thống.');
        }

        $permissionsByGroup = $this->permissionsByGroup();

        return view('admin.roles.edit', compact('role', 'permissionsByGroup'));
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, string $locale, Role $role)
    {
        if ($role->is_system) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Không thể chỉnh sửa vai trò hệ thống.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => $this->permissionRule(),
        ]);

        $oldValues = $role->only(['name', 'permissions']);
        $role->update([
            'name' => $validated['name'],
            'permissions' => $validated['permissions'] ?? [],
        ]);
        ActivityLogger::log('updated', $role, "Cập nhật vai trò {$role->name}", [
            'old' => $oldValues,
            'new' => $role->only(['name', 'permissions']),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật vai trò thành công.'
            ]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Đã cập nhật vai trò thành công.');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(string $locale, Role $role)
    {
        if ($role->is_system) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Không thể xoá vai trò hệ thống.');
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Không thể xoá vai trò này vì đang có tài khoản sử dụng.');
        }

        $changes = $role->only(['name', 'permissions']);
        $role->delete();
        ActivityLogger::log('deleted', $role, "Xóa vai trò {$changes['name']}", ['old' => $changes]);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Đã xoá vai trò thành công.');
    }
}
