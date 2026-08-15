<?php

namespace App\Models;

use App\Support\PermissionRegistry;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'is_system',
        'is_superadmin',
        'permissions',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_superadmin' => 'boolean',
    ];

    /**
     * Codes handed to the `permissions` setter, held until the row has an id to
     * attach pivot records to. Null means "nothing pending".
     *
     * @var array<int, string>|null
     */
    private ?array $pendingPermissionCodes = null;

    protected static function booted(): void
    {
        static::saved(function (Role $role): void {
            if ($role->pendingPermissionCodes === null) {
                return;
            }

            $codes = $role->pendingPermissionCodes;
            $role->pendingPermissionCodes = null;
            $role->syncPermissionCodes($codes);
        });
    }

    /**
     * The pivot rows. Use this to query or eager-load; use the `permissions`
     * attribute when you want plain codes.
     */
    public function permissionRecords(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Permission codes as a plain array, reading through to the pivot.
     *
     * Writing to it stages a sync that runs once the row is saved, which keeps
     * `Role::create(['permissions' => [...]])` working now that the JSON column
     * is gone. A superadmin role reports `['*']` for the same reason: callers
     * that predate the pivot still test for the wildcard.
     */
    protected function permissions(): Attribute
    {
        return Attribute::make(
            get: fn (): array => $this->pendingPermissionCodes ?? $this->resolvePermissionCodes(),
            set: function (mixed $value): array {
                $this->pendingPermissionCodes = array_values(array_unique(
                    array_map(strval(...), is_array($value) ? $value : (array) $value),
                ));

                // Nothing to persist on the roles row itself.
                return [];
            },
        );
    }

    public function hasPermission(string $ability): bool
    {
        return $this->is_superadmin || in_array($ability, $this->permissions, true);
    }

    /**
     * Replace this role's permissions with the given codes.
     *
     * A wildcard survives only on the superadmin role. Anywhere else it is
     * expanded to concrete codes, continuing the policy that migration
     * 2026_07_22_010000 introduced when it stripped `*` from ordinary roles.
     *
     * @param  array<int, string>  $codes
     */
    public function syncPermissionCodes(array $codes): void
    {
        $wildcard = in_array('*', $codes, true);

        if ($wildcard && ($this->is_system || $this->name === 'Superadmin')) {
            if (! $this->is_superadmin) {
                $this->forceFill(['is_superadmin' => true])->saveQuietly();
            }

            $this->permissionRecords()->detach();
            $this->setRelation('permissionRecords', new Collection());

            return;
        }

        if ($wildcard) {
            $codes = PermissionRegistry::wildcardCodes();
        }

        if ($this->is_superadmin) {
            $this->forceFill(['is_superadmin' => false])->saveQuietly();
        }

        $ids = Permission::query()
            ->whereIn('code', array_filter($codes, PermissionRegistry::has(...)))
            ->pluck('id')
            ->all();

        $this->permissionRecords()->sync($ids);
        $this->unsetRelation('permissionRecords');
    }

    /** @return array<int, string> */
    private function resolvePermissionCodes(): array
    {
        if ($this->is_superadmin) {
            return ['*'];
        }

        if (! $this->exists) {
            return [];
        }

        // Load rather than pluck: the admin sidebar asks about a dozen
        // abilities per render, and the loaded relation answers the rest.
        if (! $this->relationLoaded('permissionRecords')) {
            $this->load('permissionRecords');
        }

        return $this->permissionRecords->pluck('code')->all();
    }
}
