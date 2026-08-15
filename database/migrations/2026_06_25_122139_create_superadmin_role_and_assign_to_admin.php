<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: seed the Superadmin role and point the default admin at it.
 *
 * Deliberately written against the query builder rather than the Role/User
 * models. Models describe today's schema, and replaying this migration on a
 * fresh database must work against the schema as it stood here — once Role
 * gained pivot-backed permissions, the Eloquent version started writing columns
 * that later migrations had not created yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        $superadminRoleId = DB::table('roles')->where('name', 'Superadmin')->value('id');

        if ($superadminRoleId === null) {
            $superadminRoleId = DB::table('roles')->insertGetId([
                'name' => 'Superadmin',
                'permissions' => json_encode(['*']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');

        DB::table('users')
            ->where('email', $adminEmail)
            ->update(['role_id' => $superadminRoleId, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

        if ($adminRoleId === null) {
            return;
        }

        DB::table('users')
            ->where('email', env('ADMIN_EMAIL', 'admin@example.com'))
            ->update(['role_id' => $adminRoleId, 'updated_at' => now()]);
    }
};
