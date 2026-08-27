<?php

namespace App\Console\Commands;

use App\Models\FeatureSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckCore extends Command
{
    protected $signature = 'core:check';

    protected $description = 'Check whether the reusable admin core is ready to run';

    public function handle(): int
    {
        $hasErrors = false;

        if (blank(config('app.key'))) {
            $this->components->error('APP_KEY is not configured.');
            $hasErrors = true;
        } else {
            $this->components->twoColumnDetail('Application key', 'configured');
        }

        if (app()->environment('production') && config('app.debug')) {
            $this->components->error('APP_DEBUG must be false in production.');
            $hasErrors = true;
        }

        try {
            DB::connection()->getPdo();
            $this->components->twoColumnDetail('Database connection', 'available');
        } catch (Throwable $exception) {
            $this->components->error('Database connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $requiredTables = [
            'users',
            'roles',
            'permissions',
            'role_permission',
            'user_permissions',
            'feature_settings',
            'project_settings',
            'inventory_movements',
            'payment_transactions',
        ];

        $missingTables = array_values(array_filter(
            $requiredTables,
            fn (string $table): bool => ! Schema::hasTable($table),
        ));

        if ($missingTables !== []) {
            $this->components->error('Missing core tables: '.implode(', ', $missingTables).'. Run "php artisan migrate".');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Core schema', 'up to date');

        if (User::query()->where('is_active', true)->count() === 0) {
            $this->components->warn('No active administrator was found. Run "php artisan core:install".');
            $hasErrors = true;
        } else {
            $this->components->twoColumnDetail('Active administrators', 'available');
        }

        if (FeatureSetting::query()->count() === 0) {
            $this->components->warn('Foundation settings are missing. Run "php artisan core:install".');
            $hasErrors = true;
        } else {
            $this->components->twoColumnDetail('Foundation configuration', 'available');
        }

        if ($hasErrors) {
            return self::FAILURE;
        }

        $this->components->info('Admin core checks passed.');

        return self::SUCCESS;
    }
}
