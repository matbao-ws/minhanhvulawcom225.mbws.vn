<?php

use App\Support\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `partners.*` is new. Partner logos were edited in place on the storefront until now, and they are
 * the same kind of content as banners, so every role and per-user grant holding a `banners.<action>`
 * receives the matching `partners.<action>`: whoever managed banners keeps managing the logos.
 * Nobody else gains anything; the superadmin role needs no rows.
 */
return new class extends Migration
{
    private const ACTIONS = ['view', 'create', 'update', 'delete'];

    public function up(): void
    {
        $now = now();
        $definitions = collect(PermissionRegistry::definitions())->keyBy('code');

        foreach (self::ACTIONS as $action) {
            $code = "partners.{$action}";
            if (! $definitions->has($code)) {
                continue;
            }

            DB::table('permissions')->updateOrInsert(['code' => $code], $definitions[$code] + ['created_at' => $now, 'updated_at' => $now]);
            $partnerId = DB::table('permissions')->where('code', $code)->value('id');
            $bannerId = DB::table('permissions')->where('code', "banners.{$action}")->value('id');

            if (! $partnerId || ! $bannerId) {
                continue;
            }

            foreach (DB::table('role_permission')->where('permission_id', $bannerId)->pluck('role_id') as $roleId) {
                DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $partnerId, 'created_at' => $now, 'updated_at' => $now]);
            }

            foreach (DB::table('user_permissions')->where('permission_id', $bannerId)->get(['user_id', 'granted']) as $row) {
                DB::table('user_permissions')->insertOrIgnore(['user_id' => $row->user_id, 'permission_id' => $partnerId, 'granted' => $row->granted, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('code', array_map(fn (string $action) => "partners.{$action}", self::ACTIONS))->delete();
    }
};
