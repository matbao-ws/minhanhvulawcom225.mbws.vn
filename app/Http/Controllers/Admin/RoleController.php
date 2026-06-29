<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Get the list of all system permissions.
     */
    private function getAvailablePermissions(): array
    {
        return [
            'manage_products' => 'Quản lý sản phẩm (Catalog)',
            'manage_orders' => 'Quản lý đơn hàng',
            'manage_vouchers' => 'Quản lý mã giảm giá',
            'manage_reviews' => 'Quản lý bình luận & đánh giá',
            'manage_posts' => 'Quản lý bài viết (CMS)',
            'manage_banners' => 'Quản lý Banner',
            'manage_users' => 'Quản lý tài khoản',
            'manage_roles' => 'Quản lý vai trò & phân quyền',
            'manage_settings' => 'Cấu hình website',
        ];
    }

    /**
     * Display a listing of the roles.
     */
    public function index()
    {
        $query = Role::query()->withCount('users');
        if (! auth()->user()->isSuperAdmin()) {
            $query->where('name', '!=', 'Superadmin');
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
        $permissions = $this->getAvailablePermissions();

        return view('admin.roles.create', compact('role', 'permissions'));
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:' . implode(',', array_keys($this->getAvailablePermissions())),
        ]);

        Role::create([
            'name' => $validated['name'],
            'permissions' => $validated['permissions'] ?? [],
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
        if ($role->name === 'Superadmin') {
            if (! auth()->user()->isSuperAdmin()) {
                abort(403, 'Unauthorized.');
            }
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Không thể chỉnh sửa vai trò Superadmin tối cao.');
        }

        $permissions = $this->getAvailablePermissions();

        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, string $locale, Role $role)
    {
        if ($role->name === 'Superadmin') {
            if (! auth()->user()->isSuperAdmin()) {
                abort(403, 'Unauthorized.');
            }
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Không thể chỉnh sửa vai trò Superadmin tối cao.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:' . implode(',', array_keys($this->getAvailablePermissions())),
        ]);

        $role->update([
            'name' => $validated['name'],
            'permissions' => $validated['permissions'] ?? [],
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
        if ($role->name === 'Superadmin') {
            if (! auth()->user()->isSuperAdmin()) {
                abort(403, 'Unauthorized.');
            }
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Không thể xoá vai trò Superadmin tối cao.');
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Không thể xoá vai trò này vì đang có tài khoản sử dụng.');
        }

        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Đã xoá vai trò thành công.');
    }
}
