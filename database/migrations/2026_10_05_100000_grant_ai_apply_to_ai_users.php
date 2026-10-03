<?php

use App\Support\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `ai.apply` splits writing an AI suggestion from asking for one. Every role
 * and per-user grant that had `ai.use` keeps what it could do, so nobody
 * loses access on upgrade; an owner can then take `ai.apply` away from roles
 * that should only draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        $definition = collect(PermissionRegistry::definitions())->firstWhere('code', 'ai.apply');
        $now = now();

        DB::table('permissions')->updateOrInsert(['code' => 'ai.apply'], $definition + ['created_at' => $now, 'updated_at' => $now]);
        $apply = DB::table('permissions')->where('code', 'ai.apply')->value('id');
        $use = DB::table('permissions')->where('code', 'ai.use')->value('id');

        if (! $use) {
            return;
        }

        foreach (DB::table('role_permission')->where('permission_id', $use)->pluck('role_id') as $roleId) {
            DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $apply, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (DB::table('user_permissions')->where('permission_id', $use)->get(['user_id', 'granted']) as $row) {
            DB::table('user_permissions')->insertOrIgnore(['user_id' => $row->user_id, 'permission_id' => $apply, 'granted' => $row->granted, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('code', 'ai.apply')->delete();
    }
};
