<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->updateOrInsert(
            ['code' => 'manage_media'],
            [
                'name' => 'Quản lý thư viện tệp',
                'group' => 'system',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $permissions = DB::table('permissions')
            ->where('code', '!=', 'manage_roles')
            ->orderBy('code')
            ->pluck('code')
            ->all();

        DB::table('roles')
            ->where('is_system', false)
            ->orderBy('id')
            ->eachById(function (object $role) use ($permissions): void {
                $assigned = json_decode((string) $role->permissions, true) ?: [];
                if (in_array('*', $assigned, true)) {
                    DB::table('roles')->where('id', $role->id)->update([
                        'permissions' => json_encode($permissions),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Restoring wildcard privileges would recreate the vulnerability.
    }
};
