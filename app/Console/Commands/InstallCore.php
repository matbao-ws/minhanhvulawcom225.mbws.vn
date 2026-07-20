<?php

namespace App\Console\Commands;

use Database\Seeders\FoundationSeeder;
use Illuminate\Console\Command;

class InstallCore extends Command
{
    protected $signature = 'core:install
        {--admin-name= : Full name for the initial superadmin}
        {--admin-email= : Email address for the initial superadmin}
        {--admin-password= : Password for the initial superadmin (minimum 12 characters)}
        {--force : Confirm installation in the production environment}';

    protected $description = 'Migrate and seed the reusable admin core without demo data';

    public function handle(): int
    {
        if (blank(config('app.key'))) {
            $this->components->error('APP_KEY is missing. Run "php artisan key:generate" before installing the core.');

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->components->error('Use --force to confirm a production installation.');

            return self::FAILURE;
        }

        $name = $this->resolveName();
        $email = $this->resolveEmail();
        $password = $this->resolvePassword();

        if ($name === null || $email === null || $password === null) {
            return self::FAILURE;
        }

        $migrateOptions = app()->environment('production') ? ['--force' => true] : [];
        if ($this->call('migrate', $migrateOptions) !== self::SUCCESS) {
            $this->components->error('Database migration failed. No seed data was applied.');

            return self::FAILURE;
        }

        $originalEnvironment = $this->overrideEnvironment([
            'ADMIN_NAME' => $name,
            'ADMIN_EMAIL' => $email,
            'ADMIN_PASSWORD' => $password,
        ]);

        try {
            $seedOptions = ['--class' => FoundationSeeder::class];
            if (app()->environment('production')) {
                $seedOptions['--force'] = true;
            }

            if ($this->call('db:seed', $seedOptions) !== self::SUCCESS) {
                $this->components->error('Core seed failed. Review the command output and run core:check after fixing it.');

                return self::FAILURE;
            }
        } finally {
            $this->restoreEnvironment($originalEnvironment);
        }

        $this->components->info('Admin core installed successfully.');
        $this->line('Admin URL: '.url('/'.config('app.locale', 'vi').'/admin'));
        $this->line('Run "php artisan core:check" to verify the deployment.');

        return self::SUCCESS;
    }

    private function resolveName(): ?string
    {
        $name = trim((string) $this->option('admin-name'));

        if ($name === '' && $this->input->isInteractive()) {
            $name = trim((string) $this->ask('Initial superadmin name', 'Admin'));
        }

        if ($name === '') {
            $this->components->error('Provide --admin-name for the initial superadmin.');

            return null;
        }

        return $name;
    }

    private function resolveEmail(): ?string
    {
        $email = trim((string) $this->option('admin-email'));

        if ($email === '' && $this->input->isInteractive()) {
            $email = trim((string) $this->ask('Initial superadmin email'));
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Provide a valid --admin-email for the initial superadmin.');

            return null;
        }

        return $email;
    }

    private function resolvePassword(): ?string
    {
        $password = (string) $this->option('admin-password');

        if ($password === '' && $this->input->isInteractive()) {
            $password = (string) $this->secret('Initial superadmin password (minimum 12 characters)');
        }

        if (mb_strlen($password) < 12) {
            $this->components->error('Provide an --admin-password with at least 12 characters.');

            return null;
        }

        return $password;
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string|false>
     */
    private function overrideEnvironment(array $values): array
    {
        $original = [];

        foreach ($values as $key => $value) {
            $original[$key] = getenv($key);
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return $original;
    }

    /**
     * @param  array<string, string|false>  $values
     */
    private function restoreEnvironment(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                continue;
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
