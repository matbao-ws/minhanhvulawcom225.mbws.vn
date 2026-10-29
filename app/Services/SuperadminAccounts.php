<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The superadmin accounts the MatBao gateway console sees and can reset (GatewayCommandController).
 * Only accounts whose role carries `is_superadmin`; the password hash never leaves this site.
 */
class SuperadminAccounts
{
    public function __construct(private readonly UserAccessService $access) {}

    /**
     * @return list<array{id: int, name: string, email: string, active: bool, role: string, last_login_at: ?string}>
     */
    public function list(): array
    {
        $roles = $this->roles();

        return $this->query()->orderBy('id')->get()
            ->map(fn (User $user): array => [
                'id' => (int) $user->getKey(),
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'active' => (bool) $user->is_active,
                'role' => (string) ($roles[$user->role_id] ?? ''),
                'last_login_at' => $user->last_login_at ? Carbon::parse($user->last_login_at)->toIso8601String() : null,
            ])->values()->all();
    }

    public function find(int $id): ?User
    {
        return $this->query()->whereKey($id)->first();
    }

    /**
     * Sets the password and signs the account out everywhere: API tokens, sessions, remember-me.
     */
    public function resetPassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password' => $password])->save();
            $this->access->revoke($user);
        });

        ActivityLogger::log('superadmin.password_reset_by_gateway', $user, "Mật khẩu superadmin {$user->email} được đặt lại từ console MatBao.");
    }

    /** @return Builder<User> */
    private function query(): Builder
    {
        return User::query()->whereIn('role_id', array_keys($this->roles()));
    }

    /** @return array<int, string> superadmin role names by id */
    private function roles(): array
    {
        return Role::query()->where('is_superadmin', true)->pluck('name', 'id')->all();
    }
}
