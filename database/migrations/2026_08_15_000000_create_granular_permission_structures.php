<?php

use App\Support\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves role permissions from a JSON column of coarse `manage_*` codes onto a
 * real pivot of granular `<module>.<action>` codes, and adds per-user grants
 * and revocations on top.
 *
 * Access is preserved exactly: every legacy code is replayed through
 * {@see PermissionRegistry::expandLegacy()}, so a role that could manage
 * products keeps all four product actions and nothing more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'is_superadmin')) {
                $table->boolean('is_superadmin')->default(false)->after('is_system');
            }
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        // granted = true adds a permission the role lacks; granted = false
        // takes one away that the role grants. Both directions are needed so a
        // single account can be tuned without minting a bespoke role.
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->boolean('granted')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'permission_id']);
        });

        $this->syncCatalogue();
        $this->backfillRolePermissions();

        DB::table('permissions')->whereNotIn('code', PermissionRegistry::codes())->delete();

        if (Schema::hasColumn('roles', 'permissions')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('permissions');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('roles', 'permissions')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->json('permissions')->nullable()->after('is_system');
            });
        }

        $this->restoreLegacyJson();

        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permission');

        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'is_superadmin')) {
                $table->dropColumn('is_superadmin');
            }
        });
    }

    private function syncCatalogue(): void
    {
        $now = now();

        foreach (PermissionRegistry::definitions() as $definition) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $definition['code']],
                $definition + ['updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    private function backfillRolePermissions(): void
    {
        if (! Schema::hasColumn('roles', 'permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'code');
        $now = now();

        foreach (DB::table('roles')->orderBy('id')->get() as $role) {
            $assigned = json_decode((string) $role->permissions, true) ?: [];

            if (in_array('*', $assigned, true)) {
                // The pre-existing predicate for "this role is the superadmin".
                // Anything else holding a wildcard is expanded instead, which is
                // what migration 2026_07_22_010000 already decided.
                if ($role->is_system || $role->name === 'Superadmin') {
                    DB::table('roles')->where('id', $role->id)->update(['is_superadmin' => true]);

                    continue;
                }

                $assigned = array_keys(PermissionRegistry::legacyMap());
            }

            $codes = [];
            foreach ($assigned as $code) {
                // Already-granular codes pass through; coarse ones expand.
                $codes = array_merge($codes, PermissionRegistry::has($code)
                    ? [$code]
                    : PermissionRegistry::expandLegacy($code));
            }

            $rows = [];
            foreach (array_unique($codes) as $code) {
                if (! isset($permissionIds[$code])) {
                    continue;
                }

                $rows[] = [
                    'role_id' => $role->id,
                    'permission_id' => $permissionIds[$code],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('role_permission')->insertOrIgnore($rows);
            }
        }
    }

    private function restoreLegacyJson(): void
    {
        if (! Schema::hasTable('role_permission')) {
            return;
        }

        $codesById = DB::table('permissions')->pluck('code', 'id');

        foreach (DB::table('roles')->orderBy('id')->get() as $role) {
            if (! empty($role->is_superadmin)) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(['*'])]);

                continue;
            }

            $held = DB::table('role_permission')
                ->where('role_id', $role->id)
                ->pluck('permission_id')
                ->map(fn ($id) => $codesById[$id] ?? null)
                ->filter()
                ->all();

            // Collapse back to a coarse code only when every granular code it
            // expanded into is still present; a partially trimmed role would
            // otherwise regain access on rollback.
            $legacy = [];
            foreach (PermissionRegistry::legacyMap() as $legacyCode => $granular) {
                if ($granular !== [] && array_diff($granular, $held) === []) {
                    $legacy[] = $legacyCode;
                    $held = array_diff($held, $granular);
                }
            }

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_merge($legacy, array_values($held)))),
            ]);
        }
    }
};
