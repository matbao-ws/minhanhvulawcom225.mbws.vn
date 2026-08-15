<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Support\PermissionRegistry;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync {--prune : Also delete permissions no longer declared in the registry}';

    protected $description = 'Project App\\Support\\PermissionRegistry into the permissions table';

    public function handle(): int
    {
        $created = 0;
        $updated = 0;

        foreach (PermissionRegistry::definitions() as $definition) {
            $permission = Permission::query()->firstOrNew(['code' => $definition['code']]);
            $wasNew = ! $permission->exists;
            $permission->fill($definition);

            if ($wasNew || $permission->isDirty()) {
                $permission->save();
                $wasNew ? $created++ : $updated++;
            }
        }

        $this->info("Đã thêm {$created}, cập nhật {$updated} quyền.");

        // Pruning detaches pivot rows through the cascade, so it stays opt-in:
        // a mistyped registry entry would otherwise silently strip live roles.
        $stale = Permission::query()->whereNotIn('code', PermissionRegistry::codes())->get();

        if ($stale->isEmpty()) {
            return self::SUCCESS;
        }

        $this->warn('Quyền không còn trong registry: '.$stale->pluck('code')->implode(', '));

        if (! $this->option('prune')) {
            $this->line('Chạy lại với --prune để xoá hẳn.');

            return self::SUCCESS;
        }

        $this->info('Đã xoá '.$stale->count().' quyền cũ.');
        Permission::query()->whereKey($stale->modelKeys())->delete();

        return self::SUCCESS;
    }
}
