<?php

namespace App\Console\Commands;

use App\Support\CoreUpdate\CoreUpdater;
use App\Support\CoreUpdate\ReleaseDownloader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Updates this website's core to a signed release. Run it from the CLI (cron), never from a web
 * request: it swaps files, migrates and checks health in fresh PHP processes.
 *
 *   php artisan core:update --url=https://…/releases/1.1.0 --check   preflight only
 *   php artisan core:update --url=https://…/releases/1.1.0           install
 *   php artisan core:update --from=/path/to/release                  from a downloaded release
 *
 * Output lines UPDATE_READY / UPDATE_BLOCKED / UPDATE_INSTALLED / UPDATE_FAILED are stable, for the
 * update service to read.
 */
class CoreUpdate extends Command
{
    protected $signature = 'core:update
        {--from= : Directory holding release.json, release.json.sig and the packages}
        {--url= : Base URL holding release.json and the packages}
        {--token-env= : Environment variable with a bearer token for --url}
        {--check : Only run the preflight}
        {--backup-done : The database is backed up (needed for releases marked backup_required)}
        {--json : Print the result as JSON}';

    protected $description = 'Check or install a signed core release';

    public function handle(CoreUpdater $updater, ReleaseDownloader $downloader): int
    {
        $dir = (string) $this->option('from');
        $downloaded = null;

        if (filled($url = $this->option('url'))) {
            $downloaded = storage_path('app/core/downloads/'.date('YmdHis').'-'.substr(sha1((string) $url), 0, 8));
            try {
                $lock = base_path('composer.lock');
                $token = filled($env = $this->option('token-env')) ? (string) getenv((string) $env) : null;
                $downloader->download((string) $url, $downloaded, $token, is_file($lock) ? hash_file('sha256', $lock) : null);
            } catch (Throwable $e) {
                File::deleteDirectory($downloaded);

                return $this->finish(['ok' => false, 'error' => 'download: '.$e->getMessage()], 'UPDATE_FAILED');
            }
            $dir = $downloaded;
        }

        if ($dir === '' || ! is_dir($dir)) {
            $this->components->error('Pass --url or --from.');

            return self::FAILURE;
        }

        try {
            $options = ['backup_done' => (bool) $this->option('backup-done')];

            if ($this->option('check')) {
                ['release' => $release, 'blockers' => $blockers] = $updater->check($dir, $options);

                return $this->finish(['ok' => $blockers === [], 'to' => $release['version'] ?? null, 'blockers' => $blockers],
                    $blockers === [] ? 'UPDATE_READY' : 'UPDATE_BLOCKED');
            }

            $result = $updater->apply($dir, $options);

            return $this->finish($result, match (true) {
                $result['ok'] => 'UPDATE_INSTALLED',
                isset($result['blockers']) => 'UPDATE_BLOCKED',
                default => 'UPDATE_FAILED',
            });
        } finally {
            if ($downloaded !== null) {
                File::deleteDirectory($downloaded);
            }
        }
    }

    private function finish(array $result, string $status): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['status' => $status] + $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line($status.(isset($result['to']) ? ' '.$result['to'] : ''));
        foreach ($result['blockers'] ?? [] as $blocker) {
            $this->components->warn($blocker);
        }
        if (isset($result['error'])) {
            $this->components->error($result['error'].(($result['rolled_back'] ?? false) ? ' — the previous code was restored.' : ''));
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
