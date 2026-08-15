<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Projects {@see PermissionRegistry} into the `permissions` table.
     *
     * Codes that vanished from the registry are removed so roles cannot keep
     * pointing at abilities no route enforces any more.
     */
    public function run(): void
    {
        foreach (PermissionRegistry::definitions() as $definition) {
            Permission::query()->updateOrCreate(['code' => $definition['code']], $definition);
        }

        Permission::query()->whereNotIn('code', PermissionRegistry::codes())->delete();
    }
}
