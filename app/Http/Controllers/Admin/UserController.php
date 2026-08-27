<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesPermissionGrants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CloudinaryService;
use App\Support\MediaUrl;
use App\Support\PermissionRegistry;
use App\Services\UserAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use AuthorizesPermissionGrants;

    public function __construct(
        private readonly CloudinaryService $cloudinaryService,
        private readonly UserAccessService $userAccessService,
    )
    {
    }

    private function getAvailableRoles()
    {
        $query = Role::query();
        if (! auth()->user()->isSuperAdmin()) {
            $query->where('is_system', false)->where('is_superadmin', false);
        }
        return $query->get();
    }

    private function assertCanManage(User $user): void
    {
        if ($user->hasPrivilegedRole() && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized.');
        }
    }

    private function assertCanAssignRole(?Role $role): void
    {
        if (! $role || ($role->is_system || $role->is_superadmin) && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized.');
        }
    }

    /**
     * Apply the per-account grants and revocations submitted with the form.
     *
     * Nobody edits their own overrides — that would be a one-click self
     * promotion — and no code outside the actor's own reach may be touched, in
     * either direction.
     *
     * @param  array<string, string>  $submitted  code => grant|revoke|inherit
     * @return bool  whether the effective access changed
     */
    private function syncPermissionOverrides(User $user, array $submitted): bool
    {
        if ($user->is(auth()->user())) {
            abort_if($submitted !== [], 403, 'Không thể tự sửa quyền riêng của chính mình.');

            return false;
        }

        $grantable = $this->grantableCodes();
        $desired = [];

        foreach ($submitted as $code => $state) {
            if (! in_array($state, ['grant', 'revoke'], true) || ! PermissionRegistry::has($code)) {
                continue;
            }

            $this->assertCanGrant([$code]);
            $desired[$code] = $state === 'grant';
        }

        $permissionIds = Permission::query()->pluck('id', 'code');
        $existing = $user->permissionOverrides()->pluck('user_permissions.granted', 'permissions.code')
            ->map(fn ($granted): bool => (bool) $granted)
            ->all();

        // Overrides on codes the actor cannot reach stay exactly as they are.
        $sync = [];
        foreach ($existing as $code => $granted) {
            if (! in_array($code, $grantable, true) && isset($permissionIds[$code])) {
                $sync[$permissionIds[$code]] = ['granted' => $granted];
            }
        }
        foreach ($desired as $code => $granted) {
            if (isset($permissionIds[$code])) {
                $sync[$permissionIds[$code]] = ['granted' => $granted];
            }
        }

        $user->permissionOverrides()->sync($sync);
        $user->forgetPermissionOverrideCache();

        $before = $existing;
        ksort($before);
        $after = $desired + array_filter($existing, fn ($granted, $code): bool => ! in_array($code, $grantable, true), ARRAY_FILTER_USE_BOTH);
        ksort($after);

        return $before !== $after;
    }

    public function index(Request $request)
    {
        // This screen manages staff accounts only. Customers are users with no role
        // and belong on the customers screen; without this a superadmin saw every
        // shopper listed here, because the visibility filter below — which excludes
        // them as a side effect of joining `roles` — only applies to everyone else.
        $query = User::query()
            ->with('role')
            ->whereNotNull('role_id');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereHas('role', function ($q) {
                $q->where('is_system', false)->where('is_superadmin', false);
            });
        }

        $users = $query
            ->when($request->query('q'), function ($query, $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->when($request->query('role_id'), function ($query, $roleId) {
                $query->where('role_id', $roleId);
            })
            ->when($request->filled('status'), function ($query) {
                $query->where('is_active', request('status'));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $roles = $this->getAvailableRoles();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function create()
    {
        return view('admin.users.create', [
            'user' => new User([
                'is_active' => true,
            ]),
            'roles' => $this->getAvailableRoles(),
            'overrideModules' => $this->grantableModules(),
            'currentOverrides' => [],
        ]);
    }

    public function store(UserRequest $request)
    {
        $this->assertCanAssignRole(Role::find($request->input('role_id')));

        $data = $request->validated();
        
        if ($request->hasFile('avatar_file')) {
            $data['avatar_url'] = $this->cloudinaryService->uploadFile($request->file('avatar_file'), 'avatars');
        }

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = (bool) ($request->input('is_active', false));

        $user = User::query()->create($data);
        $this->syncPermissionOverrides($user, (array) $request->input('permission_overrides', []));
        ActivityLogger::log('created', $user, "Tạo tài khoản {$user->email}", ['new' => $user->only(['name', 'email', 'role_id', 'is_active'])]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('admin.users.created'));
    }

    public function edit(string $locale, User $user)
    {
        $this->assertCanManage($user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->getAvailableRoles(),
            // Self-editing never shows the override matrix; the controller
            // rejects it anyway.
            'overrideModules' => $user->is(auth()->user()) ? [] : $this->grantableModules(),
            'currentOverrides' => $user->permissionOverrideMap(),
        ]);
    }

    public function update(UserRequest $request, string $locale, User $user)
    {
        $this->assertCanManage($user);
        $this->assertCanAssignRole(Role::find($request->input('role_id')));

        if ($user->is(auth()->user()) && ((int) $request->input('role_id') !== (int) $user->role_id || (bool) $request->input('is_active', false) !== $user->is_active)) {
            abort(403, 'You cannot change your own role or account status.');
        }

        $data = $request->validated();
        $oldValues = $user->only(['name', 'email', 'role_id', 'is_active']);

        if ($request->hasFile('avatar_file')) {
            // Delete old avatar if it exists (optional)
            if (!empty($user->avatar_url) && !$this->cloudinaryService->isConfigured()) {
                // local fallback path parsing and deletion
                $path = MediaUrl::publicDiskPath($user->avatar_url);
                if ($path !== null) {
                    $this->cloudinaryService->deleteResource($path);
                }
            }
            $data['avatar_url'] = $this->cloudinaryService->uploadFile($request->file('avatar_file'), 'avatars');
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['is_active'] = (bool) ($request->input('is_active', false));

        $accessChanged = array_key_exists('password', $data)
            || (int) $data['role_id'] !== (int) $user->role_id
            || (bool) $data['is_active'] !== $user->is_active;

        $user->update($data);
        $accessChanged = $this->syncPermissionOverrides($user, (array) $request->input('permission_overrides', []))
            || $accessChanged;
        if ($accessChanged) {
            $this->userAccessService->revoke($user, $user->is(auth()->user()) ? $request->session()->getId() : null);
        }
        ActivityLogger::log('updated', $user, "Cập nhật tài khoản {$user->email}", [
            'old' => $oldValues,
            'new' => $user->only(['name', 'email', 'role_id', 'is_active']),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('admin.users.updated'));
    }

    public function destroy(string $locale, User $user)
    {
        $this->assertCanManage($user);

        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('admin.users.delete_self_error'));
        }

        // Delete avatar if it exists
        if (!empty($user->avatar_url)) {
            $path = MediaUrl::publicDiskPath($user->avatar_url);
            if ($path !== null) {
                $this->cloudinaryService->deleteResource($path);
            }
        }

        $oldValues = $user->only(['name', 'email', 'role_id', 'is_active']);
        $this->userAccessService->revoke($user);
        $user->delete();
        ActivityLogger::log('deleted', $user, "Xóa tài khoản {$oldValues['email']}", ['old' => $oldValues]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('admin.users.deleted'));
    }

    public function show(string $locale, User $user)
    {
        $this->assertCanManage($user);

        return redirect()->route('admin.users.edit', $user);
    }

    public function toggleStatus(string $locale, User $user)
    {
        $this->assertCanManage($user);

        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('admin.users.toggle_status_self_error'));
        }

        $user->is_active = ! $user->is_active;
        $user->save();
        $this->userAccessService->revoke($user);
        ActivityLogger::log('status_changed', $user, "Thay đổi trạng thái tài khoản {$user->email}", [
            'new' => ['is_active' => $user->is_active],
        ]);

        $message = $user->is_active 
            ? __('admin.users.unlocked') 
            : __('admin.users.locked');

        return redirect()
            ->route('admin.users.index')
            ->with('success', $message);
    }

    public function impersonate(Request $request, string $locale, User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Bạn không thể đăng nhập nhanh vào tài khoản của chính mình.');
        }

        if (! $user->is_active || $user->hasPrivilegedRole()) {
            abort(403, 'Only active non-privileged accounts can be impersonated.');
        }

        $originalAdminId = auth()->id();
        auth()->login($user);
        $request->session()->regenerate();
        $request->session()->put('impersonated_by', $originalAdminId);
        ActivityLogger::log('impersonated', $user, "Đăng nhập nhanh vào tài khoản {$user->email}");

        // Redirect to homepage if target has no admin privileges, else to dashboard
        if ($user->role_id === null) {
            return redirect('/')
                ->with('success', 'Bạn đã đăng nhập nhanh thành công dưới vai trò ' . $user->name);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Bạn đã đăng nhập nhanh thành công dưới vai trò ' . $user->name);
    }

    public function leaveImpersonate(Request $request)
    {
        if (! session()->has('impersonated_by')) {
            abort(403, 'Unauthorized.');
        }

        $adminId = session()->get('impersonated_by');
        $admin = User::findOrFail($adminId);
        abort_unless($admin->is_active && $admin->isSuperAdmin(), 403, 'Unauthorized.');

        auth()->login($admin);
        $request->session()->regenerate();
        $request->session()->forget('impersonated_by');

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Đã quay lại tài khoản quản trị viên thành công.');
    }
}
