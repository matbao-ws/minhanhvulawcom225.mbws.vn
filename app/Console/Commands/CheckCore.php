<?php

namespace App\Console\Commands;

use App\Models\FeatureSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
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

        $this->reportPackage();

        $this->reportEditableCoverage();

        if ($hasErrors) {
            return self::FAILURE;
        }

        $this->components->info('Admin core checks passed.');

        return self::SUCCESS;
    }

    /**
     * Which package the shop runs on. Read from the stored snapshot only: this
     * command is read-only and must not call out to the internal tool.
     * Reported, never fatal — manual feature control is a valid setup.
     */
    private function reportPackage(): void
    {
        if (! app(\App\Services\Packages\PackageInfo::class)->configured()) {
            $this->components->twoColumnDetail('Service package', 'manual (PACKAGE_INFO_DOMAIN not set)');

            return;
        }

        $package = app(\App\Services\Packages\PackageSync::class)->snapshot();

        if ($package === null) {
            $this->components->warn('No package synced yet for '.config('packages.domain').'. Run "php artisan package:sync".');

            return;
        }

        $this->components->twoColumnDetail('Service package', $package['code'].' — '.$package['name']);

        if (! ($package['plan_known'] ?? false)) {
            $this->components->warn('Package '.$package['code'].' has no entry in config/packages.php; features are not managed by the package.');
        }
    }

    /**
     * Whether any storefront copy is still invisible to the inline editor.
     *
     * Reported, never fatal. A shop runs perfectly well with unmarked copy —
     * the consequence is that an admin cannot edit it, which is a decision
     * someone may have made on purpose. `client:editable-audit` is where that
     * gets resolved; this line only makes sure nobody has to remember to look.
     */
    private function reportEditableCoverage(): void
    {
        if (! is_dir(resource_path('views/client'))) {
            return;
        }

        // Delegated rather than reimplemented: two walks over the same views
        // would drift, and the one in the audit command is the one its own
        // drift test exercises.
        $status = Artisan::call('client:editable-audit', [], new BufferedOutput);

        if ($status === self::SUCCESS) {
            $this->components->twoColumnDetail('Storefront editable coverage', 'complete');

            return;
        }

        $this->components->warn('Some storefront copy is not editable. Run "php artisan client:editable-audit".');
    }
}
