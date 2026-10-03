<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Run by the updater in a fresh process once the new code is in place: if this fails, the update
 * is rolled back. Keep it fast and free of side effects.
 */
class CoreHealth extends Command
{
    protected $signature = 'core:health';

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

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }
        if ($problems === []) {
            $this->components->info('Healthy.');
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
