<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasMediaUrls;
use App\Support\PermissionRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasMediaUrls, Notifiable;

    /** @var array<string, bool>|null */
    private ?array $permissionOverrideMap = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'name',
        'email',
        'preferred_locale',
        'avatar_url',
        'password',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected function avatarUrl(): Attribute
    {
        return self::mediaUrlAttribute();
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class);
    }

    /**
     * Per-account grants and revocations layered on top of the role.
     */
    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withPivot('granted');
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->role?->is_superadmin;
    }

    public function hasPrivilegedRole(): bool
    {
        return $this->role !== null
            && ($this->role->is_system || $this->role->is_superadmin);
    }

    /**
     * Overrides keyed by permission code: true grants, false revokes.
     *
     * Memoised because the gate consults it once per ability check and the
     * admin sidebar checks a dozen abilities on every render.
     *
     * @return array<string, bool>
     */
    public function permissionOverrideMap(): array
    {
        if ($this->permissionOverrideMap !== null) {
            return $this->permissionOverrideMap;
        }

        if (! $this->exists) {
            return $this->permissionOverrideMap = [];
        }

        return $this->permissionOverrideMap = $this->permissionOverrides()
            ->pluck('user_permissions.granted', 'permissions.code')
            ->map(fn ($granted): bool => (bool) $granted)
            ->all();
    }

    /**
     * Whether an override decides this ability outright.
     *
     * @return bool|null  true grants, false denies, null defers to the role
     */
    public function permissionOverrideFor(string $ability): ?bool
    {
        return $this->permissionOverrideMap()[$ability] ?? null;
    }

    /**
     * Everything this account may actually do, role and overrides combined.
     *
     * @return array<int, string>
     */
    public function effectivePermissionCodes(): array
    {
        if ($this->isSuperAdmin()) {
            return PermissionRegistry::codes();
        }

        $codes = $this->role?->permissions ?? [];

        foreach ($this->permissionOverrideMap() as $code => $granted) {
            $codes = $granted
                ? array_merge($codes, [$code])
                : array_diff($codes, [$code]);
        }

        return array_values(array_unique(array_filter($codes, PermissionRegistry::has(...))));
    }

    /**
     * Whether this account may edit page content in place on the storefront.
     *
     * The gate lives here so the page view and the editor partial cannot drift:
     * a guest must receive no admin bar, no editor script and no `data-*` hook.
     * `can()` alone is not enough — the permission gate does not know about
     * deactivated accounts.
     */
    public function canEditClientContent(): bool
    {
        return $this->is_active
            && $this->role_id !== null
            && $this->can('pages.update');
    }

    public function forgetPermissionOverrideCache(): void
    {
        $this->permissionOverrideMap = null;
        $this->unsetRelation('permissionOverrides');
    }
}
