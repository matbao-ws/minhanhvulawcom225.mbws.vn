<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesPermissionGrants;
use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\ActivityLogger;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    use AuthorizesPermissionGrants;
    use HandlesPagination;

    /**
     * Codes come from the registry rather than the table so an unseeded
     * database rejects input with a real message instead of an empty `in:` rule
     * that silently refuses everything.
     *
     * @return array<int, mixed>
     */
    private function permissionRule(): array
    {
        return ['string', Rule::in(PermissionRegistry::codes())];
    }

    /**
     * A role nobody but a superadmin may read, write or delete.
     */
    private function assertManageable(Role $role): bool
    {
        return ! (($role->is_system || $role->is_superadmin) && ! auth()->user()->isSuperAdmin());
    }

    public function index()
    {
        $query = Role::query()->with('permissionRecords')->withCount('users');
        if (! auth()->user()->isSuperAdmin()) {
            $query->where('is_system', false)->where('is_superadmin', false);
        }
        $roles = $query->orderBy('id')->paginate($this->perPage(15))->withQueryString();

        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $role = new Role;

        return view('admin.roles.create', [
            'role' => $role,
            'modules' => $this->grantableModules(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => $this->permissionRule(),
        ]);

        $permissions = $validated['permissions'] ?? [];
        $this->assertCanGrant($permissions);

        $role = Role::create([
            'name' => $validated['name'],
            'permissions' => $permissions,
        ]);
        ActivityLogger::log('created', $role, __('admin.roles.activity_created', ['name' => $role->name]), [
            'new' => ['name' => $role->name, 'permissions' => $role->permissions],
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.roles.created_new'),
            ]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('admin.roles.created_new'));
    }

    public function edit(string $locale, Role $role)
    {
        if (! $this->assertManageable($role)) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', __('admin.roles.system_not_editable'));
        }

        return view('admin.roles.edit', [
            'role' => $role,
            'modules' => $this->grantableModules(),
        ]);
    }

    public function update(Request $request, string $locale, Role $role)
    {
        if (! $this->assertManageable($role)) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', __('admin.roles.system_not_editable'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => $this->permissionRule(),
        ]);

        $oldValues = $role->only(['name', 'permissions']);
        $submitted = $validated['permissions'] ?? [];

        // Only newly added codes need to be within reach. Otherwise an admin who
        // may edit a role could never save it without first holding every
        // permission somebody else already granted it.
        $this->assertCanGrant(array_diff($submitted, $oldValues['permissions']));

        // Codes the actor cannot grant are also codes they cannot strip, so
        // anything outside their reach is carried over untouched.
        $retained = array_diff($oldValues['permissions'], $this->grantableCodes());

        $role->update([
            'name' => $validated['name'],
            'permissions' => array_values(array_unique(array_merge($submitted, $retained))),
        ]);
        ActivityLogger::log('updated', $role, __('admin.roles.activity_updated', ['name' => $role->name]), [
            'old' => $oldValues,
            'new' => $role->only(['name', 'permissions']),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.roles.updated'),
            ]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('admin.roles.updated'));
    }

    public function destroy(string $locale, Role $role)
    {
        if (! $this->assertManageable($role)) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', __('admin.roles.system_not_deletable'));
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', __('admin.roles.delete_in_use'));
        }

        $changes = $role->only(['name', 'permissions']);
        $role->delete();
        ActivityLogger::log('deleted', $role, __('admin.roles.activity_deleted', ['name' => $changes['name']]), ['old' => $changes]);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('admin.roles.destroyed'));
    }
}
