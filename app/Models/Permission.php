<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'code',
        'name',
        'group',
        'description',
    ];

    /**
     * Rows for registry codes the table does not have yet — an extension's permissions appear
     * when it is installed, and nothing runs permissions:sync on a website. Called right before
     * granting, so a grant is never silently dropped.
     *
     * @param  array<int, string>  $codes
     */
    public static function ensureRegistered(array $codes): void
    {
        $missing = array_diff($codes, static::query()->whereIn('code', $codes)->pluck('code')->all());
        if ($missing === []) {
            return;
        }
        foreach (\App\Support\PermissionRegistry::definitions() as $definition) {
            if (in_array($definition['code'], $missing, true)) {
                static::query()->firstOrCreate(['code' => $definition['code']], $definition);
            }
        }
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
