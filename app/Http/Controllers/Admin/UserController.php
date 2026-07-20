<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private readonly CloudinaryService $cloudinaryService)
    {
    }

    private function getAvailableRoles()
    {
        $query = Role::query();
        if (! auth()->user()->isSuperAdmin()) {
            $query->where('name', '!=', 'Superadmin');
        }
        return $query->get();
    }

    public function index(Request $request)
    {
        $query = User::query()
            ->with('role');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereHas('role', function ($q) {
                $q->where('name', '!=', 'Superadmin');
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
        ]);
    }

    public function store(UserRequest $request)
    {
        if ($request->filled('role_id')) {
            $selectedRole = Role::find($request->input('role_id'));
            if ($selectedRole && $selectedRole->name === 'Superadmin' && !auth()->user()->isSuperAdmin()) {
                abort(403, 'Unauthorized.');
            }
        }

        $data = $request->validated();
        
        if ($request->hasFile('avatar_file')) {
            $data['avatar_url'] = $this->cloudinaryService->uploadFile($request->file('avatar_file'), 'avatars');
        }

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = (bool) ($request->input('is_active', false));

        User::query()->create($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('admin.users.created'));
    }

    public function edit(string $locale, User $user)
    {
        if ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized.');
        }

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->getAvailableRoles(),
        ]);
    }

    public function update(UserRequest $request, string $locale, User $user)
    {
        if ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized.');
        }

        if ($request->filled('role_id')) {
            $selectedRole = Role::find($request->input('role_id'));
            if ($selectedRole && $selectedRole->name === 'Superadmin' && !auth()->user()->isSuperAdmin()) {
                abort(403, 'Unauthorized.');
            }
        }

        $data = $request->validated();

        if ($request->hasFile('avatar_file')) {
            // Delete old avatar if it exists (optional)
            if (!empty($user->avatar_url) && !$this->cloudinaryService->isConfigured()) {
                // local fallback path parsing and deletion
                $path = str_replace(asset('storage/'), '', $user->avatar_url);
                $this->cloudinaryService->deleteResource($path);
            }
            $data['avatar_url'] = $this->cloudinaryService->uploadFile($request->file('avatar_file'), 'avatars');
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['is_active'] = (bool) ($request->input('is_active', false));

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('admin.users.updated'));
    }

    public function destroy(string $locale, User $user)
    {
        if ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized.');
        }

        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('admin.users.delete_self_error'));
        }

        // Delete avatar if it exists
        if (!empty($user->avatar_url)) {
            $path = str_replace(asset('storage/'), '', $user->avatar_url);
            $this->cloudinaryService->deleteResource($path);
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('admin.users.deleted'));
    }

    public function show(string $locale, User $user)
    {
        if ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized.');
        }

        return redirect()->route('admin.users.edit', $user);
    }

    public function toggleStatus(string $locale, User $user)
    {
        if ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized.');
        }

        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('admin.users.toggle_status_self_error'));
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $message = $user->is_active 
            ? __('admin.users.unlocked') 
            : __('admin.users.locked');

        return redirect()
            ->route('admin.users.index')
            ->with('success', $message);
    }

    public function impersonate(string $locale, User $user)
    {
        if ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized.');
        }

        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Bạn không thể đăng nhập nhanh vào tài khoản của chính mình.');
        }

        // Save original admin ID in session
        session()->put('impersonated_by', auth()->id());

        // Login as target user
        auth()->login($user);

        // Redirect to homepage if target has no admin privileges, else to dashboard
        if ($user->role_id === null) {
            return redirect('/')
                ->with('success', 'Bạn đã đăng nhập nhanh thành công dưới vai trò ' . $user->name);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Bạn đã đăng nhập nhanh thành công dưới vai trò ' . $user->name);
    }

    public function leaveImpersonate()
    {
        if (! session()->has('impersonated_by')) {
            abort(403, 'Unauthorized.');
        }

        $adminId = session()->get('impersonated_by');
        $admin = User::findOrFail($adminId);

        // Login back as admin
        auth()->login($admin);

        // Forget impersonated session
        session()->forget('impersonated_by');

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Đã quay lại tài khoản quản trị viên thành công.');
    }
}
