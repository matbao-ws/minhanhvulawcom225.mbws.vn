<?php

namespace App\Console\Commands;

use App\Support\Extensions\ExtensionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Run by the updater in a fresh process once the new code is in place: if this fails, the update
 * is rolled back. Keep it fast and free of side effects.
 *
 * --extensions: the extensions that worked before the update and must still load. The updater
 * passes it, and every later core version keeps accepting it (an older updater calls the newer
 * core:health).
 */
class CoreHealth extends Command
{
    protected $signature = 'core:health {--extensions= : Comma-separated extension ids that must have loaded}';

    protected $description = 'Check that this website boots: database, migrations, routes, core views';

    public function handle(): int
    {
        $problems = [];

        try {
            DB::connection()->getPdo();

            $migrator = app('migrator');
            $ran = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];
            $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $ran);
            if ($pending !== []) {
                $problems[] = count($pending).' migration(s) not run';
            }
        } catch (Throwable $e) {
            $problems[] = 'database: '.$e->getMessage();
        }

        if (Route::getRoutes()->count() === 0 || ! Route::has('admin.login')) {
            $problems[] = 'routes not loaded';
        }
        foreach (['core.editor', 'core.head', 'admin.layouts.app'] as $view) {
            if (! view()->exists($view)) {
                $problems[] = "view {$view} missing";
            }
        }

        $extensions = app(ExtensionManager::class)->all();
        foreach (array_filter(explode(',', (string) $this->option('extensions'))) as $id) {
            $status = $extensions[$id]['status'] ?? 'missing';
            if ($status !== ExtensionManager::LOADED) {
                $problems[] = "extension {$id} did not load ({$status}".(empty($extensions[$id]['error']) ? '' : ': '.$extensions[$id]['error']).')';
            }
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }
        if ($problems === []) {
            $this->components->info('Healthy.');
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
