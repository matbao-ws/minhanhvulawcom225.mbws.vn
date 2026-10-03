<?php

namespace App\Support\CoreUpdate;

use App\Support\CoreManifest;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Installs a verified core release on this website and takes it back out when anything fails.
 *
 *   check()     preflight: every reason the update must not start (UPDATE_BLOCKED)
 *   apply()     lock → back up the files it will touch → stage → maintenance mode → swap vendor/
 *               and core files → package:discover, migrate, optimize:clear, core:health in a
 *               fresh process → record installed.json → out of maintenance
 *   rollback()  put the backed-up CODE back (after a failure, an interrupted run, or by hand)
 *
 * Rollback never touches the database: migrations are expand-only (MigrationSafety), so the old
 * code runs on the new schema. A release that is not rollback-safe needs a confirmed backup.
 * Theme and website files are never written: the release only holds core files (ReleaseVerifier).
 *
 * Everything lives in storage/app/core: installed.json, state.json, history.jsonl, backups/,
 * staging/, update.lock.
 */
class CoreUpdater
{
    private const KEEP_BACKUPS = 3;

    /** @var resource|null */
    private $lock = null;

    public function __construct(
        private readonly CoreManifest $manifest,
        private readonly ReleaseVerifier $verifier,
        private readonly ArtisanRunner $artisan,
    ) {}

    /**
     * @param  array{backup_done?: bool}  $options
     * @return array{release: ?array, blockers: list<string>}
     */
    public function check(string $dir, array $options = []): array
    {
        try {
            $release = $this->verifier->verify($dir);
        } catch (ReleaseInvalid $e) {
            return ['release' => null, 'blockers' => ['invalid_release: '.$e->getMessage()]];
        }

        $blockers = [];
        $current = $this->manifest->version();

        if ($this->lock === null && $this->isLocked()) {
            $blockers[] = 'locked: another update is running';
        }
        if (($this->state()['status'] ?? null) === 'running' && ($this->lock !== null || ! $this->isLocked())) {
            $blockers[] = 'interrupted: a previous update stopped half-way; run `php artisan core:rollback`';
        }
        if (Semver::compare($release['version'], $current) <= 0) {
            $blockers[] = "not_newer: {$release['version']} is not newer than the installed {$current}";
        }
        $minFrom = (string) ($release['requires']['min_from'] ?? '0.0.0');
        if (Semver::compare($current, $minFrom) < 0) {
            $blockers[] = "min_from: {$release['version']} needs {$minFrom} or later installed first (this website runs {$current})";
        }
        $php = (string) ($release['requires']['php'] ?? '');
        if ($php !== '' && ! Semver::satisfies(PHP_VERSION, $php)) {
            $blockers[] = "php: needs PHP {$php}, this website runs ".PHP_VERSION;
        }

        $drift = $this->manifest->drift();
        if ($drift === null) {
            $blockers[] = 'no_baseline: run `php artisan core:status --baseline` once the core files are as shipped';
        } elseif (($edited = array_merge($drift['modified'], $drift['missing'], $drift['added'])) !== []) {
            $blockers[] = sprintf('drift: %d core file(s) edited on this website (%s); move the change into the theme or core first',
                count($edited), implode(', ', array_slice($edited, 0, 5)));
        }

        $db = $release['database'] ?? [];
        if ((($db['backup_required'] ?? false) || ! ($db['rollback_safe'] ?? true)) && ! ($options['backup_done'] ?? false)) {
            $blockers[] = 'backup_required: back up the database (Plesk/cPanel backup), then run again with --backup-done';
        }

        $needsDeps = $this->needsDependencies($release);
        if ($needsDeps && ! is_file($dir.'/'.$release['artifacts']['dependencies']['file'])) {
            $blockers[] = 'dependencies_missing: composer.lock changed and the dependencies package was not downloaded';
        }

        $plan = $this->plan($release);
        $unwritable = array_values(array_filter(
            array_merge($plan['replace'], $plan['add'], $plan['remove']),
            fn (string $path) => ! $this->writable($this->base().'/'.$path),
        ));
        if ($needsDeps && ! is_writable($this->base())) {
            $unwritable[] = '(site root, for vendor/)';
        }
        if ($unwritable !== []) {
            $blockers[] = sprintf('not_writable: %d path(s), e.g. %s', count($unwritable), implode(', ', array_slice($unwritable, 0, 3)));
        }

        $needed = 3 * (($release['artifacts']['core']['size'] ?? 0) + ($needsDeps ? ($release['artifacts']['dependencies']['size'] ?? 0) : 0));
        $free = @disk_free_space($this->base());
        if ($free !== false && $free < $needed) {
            $blockers[] = sprintf('disk_space: %d MB free, %d MB needed', $free / 1048576, $needed / 1048576);
        }

        return ['release' => $release, 'blockers' => $blockers];
    }

    /**
     * @param  array{backup_done?: bool}  $options
     * @return array{ok: bool, from: string, to: ?string, blockers?: list<string>, error?: string, rolled_back?: bool}
     */
    public function apply(string $dir, array $options = []): array
    {
        $from = $this->manifest->version();
        if (! $this->acquireLock()) {
            return ['ok' => false, 'from' => $from, 'to' => null, 'blockers' => ['locked: another update is running']];
        }

        try {
            ['release' => $release, 'blockers' => $blockers] = $this->check($dir, $options);
            if ($blockers !== []) {
                return ['ok' => false, 'from' => $from, 'to' => $release['version'] ?? null, 'blockers' => $blockers];
            }

            return $this->install($dir, $release, $from);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Put back the code of the latest update that was not rolled back yet.
     *
     * @return array{ok: bool, restored?: string, error?: string}
     */
    public function rollback(): array
    {
        if (! $this->acquireLock()) {
            return ['ok' => false, 'error' => 'locked: an update is running'];
        }

        try {
            $meta = $this->latestBackup();
            if ($meta === null) {
                return ['ok' => false, 'error' => 'No update to roll back.'];
            }

            $this->restore($meta);
            if ($meta['touched'] ?? true) {
                // Back up unless the site was already in maintenance before that update started.
                $this->afterRestore($meta, (bool) ($meta['was_down'] ?? false));
            }
            $this->writeState(['status' => 'rolled_back', 'from' => $meta['to'], 'to' => $meta['from'], 'step' => null, 'error' => null]);
            $this->history(['event' => 'rollback', 'from' => $meta['to'], 'to' => $meta['from']]);

            return ['ok' => true, 'restored' => $meta['from']];
        } catch (Throwable $e) {
            $this->writeState(['status' => 'failed', 'step' => 'rollback', 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            $this->releaseLock();
        }
    }

    /** @return array{status?: string, from?: string, to?: string, step?: ?string, error?: ?string, updated_at?: string} */
    public function state(): array
    {
        $file = $this->coreDir().'/state.json';

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }

    private function install(string $dir, array $release, string $from): array
    {
        $to = $release['version'];
        $id = date('YmdHis').'-'.$from.'-to-'.$to;
        $backup = $this->coreDir().'/backups/'.$id;
        $staging = $this->coreDir().'/staging/'.$id;
        $plan = $this->plan($release);
        $needsDeps = $this->needsDependencies($release);
        $wasDown = $this->isDown();

        $meta = [
            'id' => $id,
            'from' => $from,
            'to' => $to,
            'backed_up' => array_merge($plan['replace'], $plan['remove']),
            'added' => $plan['add'],
            'vendor' => false,
            'was_down' => $wasDown,
            'config_cached' => is_file($this->base().'/bootstrap/cache/config.php'),
            // false until the first file of the site changes: an untouched run needs no restore.
            'touched' => false,
            'rolled_back' => false,
            'started_at' => now()->toIso8601String(),
        ];

        $step = 'prepare';
        try {
            $this->writeState(['status' => 'running', 'from' => $from, 'to' => $to, 'step' => $step, 'error' => null]);
            $this->artisan->warm();

            // 1. Back up what will be overwritten or deleted, and the record of what was installed.
            $step = 'backup';
            File::ensureDirectoryExists($backup.'/files');
            $this->writeMeta($backup, $meta);
            foreach ($meta['backed_up'] as $path) {
                File::ensureDirectoryExists(dirname($backup.'/files/'.$path));
                if (! copy($this->base().'/'.$path, $backup.'/files/'.$path)) {
                    throw new RuntimeException("Cannot back up {$path}");
                }
            }
            if (is_file($this->manifest->installedPath())) {
                copy($this->manifest->installedPath(), $backup.'/installed.json');
            }
            $this->writeMeta($backup, $meta);

            // 2. Stage the new files next to the site, still untouched.
            $step = 'stage';
            $this->extract($dir.'/'.$release['artifacts']['core']['file'], $staging.'/core');
            if ($needsDeps) {
                $this->extract($dir.'/'.$release['artifacts']['dependencies']['file'], $staging.'/deps');
            }

            // 3. Swap. From here on a failure restores the backup.
            $step = 'maintenance';
            $meta['touched'] = true;
            $this->writeMeta($backup, $meta);
            if (! $wasDown) {
                $this->artisan->run(['down', '--retry=60']);
            }

            $step = 'swap';
            if ($needsDeps) {
                $meta['vendor'] = true; // before the move: an interrupted run must know to move it back
                $this->writeMeta($backup, $meta);
                if (is_dir($this->base().'/vendor') && ! rename($this->base().'/vendor', $backup.'/vendor')) {
                    throw new RuntimeException('Cannot move vendor/ aside');
                }
                if (! rename($staging.'/deps/vendor', $this->base().'/vendor')) {
                    throw new RuntimeException('Cannot move the new vendor/ in place');
                }
            }
            foreach (array_merge($plan['replace'], $plan['add']) as $path) {
                $this->place($staging.'/core/'.$path, $this->base().'/'.$path);
            }
            foreach ($plan['remove'] as $path) {
                @unlink($this->base().'/'.$path);
            }

            // 4. Boot the new code in its own process.
            foreach ([['package:discover'], ['migrate', '--force'], ['optimize:clear']] as $command) {
                $step = $command[0];
                $this->writeState(['status' => 'running', 'from' => $from, 'to' => $to, 'step' => $step, 'error' => null]);
                $this->artisan->run($command);
            }
            if ($meta['config_cached']) {
                $step = 'optimize';
                $this->artisan->run(['optimize']);
            }
            $step = 'health';
            $this->artisan->run(['core:health']);

            // 5. Done.
            $this->manifest->writeInstalled(['version' => $to, 'files' => $release['files']]);
            if (! $wasDown) {
                $this->artisan->run(['up']);
            }
        } catch (Throwable $e) {
            $error = "{$step}: ".$e->getMessage();
            $rolledBack = false;
            if (! in_array($step, ['prepare', 'backup', 'stage'], true)) {
                try {
                    $this->restore($meta + ['dir' => $backup]);
                    $this->afterRestore($meta, $wasDown);
                    $rolledBack = true;
                } catch (Throwable $restoreError) {
                    $error .= ' | rollback failed: '.$restoreError->getMessage();
                }
            }
            File::deleteDirectory($staging);
            if (! $rolledBack && in_array($step, ['prepare', 'backup', 'stage'], true)) {
                File::deleteDirectory($backup); // nothing was touched
            }
            $this->writeState(['status' => 'failed', 'from' => $from, 'to' => $to, 'step' => $step, 'error' => $error]);
            $this->history(['event' => 'failed', 'from' => $from, 'to' => $to, 'step' => $step, 'error' => $error, 'rolled_back' => $rolledBack]);

            return ['ok' => false, 'from' => $from, 'to' => $to, 'error' => $error, 'rolled_back' => $rolledBack];
        }

        File::deleteDirectory($staging);
        $this->writeState(['status' => 'installed', 'from' => $from, 'to' => $to, 'step' => null, 'error' => null]);
        $this->history(['event' => 'installed', 'from' => $from, 'to' => $to]);
        $this->pruneBackups();

        return ['ok' => true, 'from' => $from, 'to' => $to];
    }

    /** Back to the files (and vendor/) from before the update; the database stays as it is. */
    private function restore(array $meta): void
    {
        $backup = $meta['dir'] ?? $this->coreDir().'/backups/'.$meta['id'];
        if (! ($meta['touched'] ?? true)) {
            $this->writeMeta($backup, ['rolled_back' => true] + $meta);

            return;
        }

        foreach ($meta['added'] as $path) {
            @unlink($this->base().'/'.$path);
        }
        foreach ($meta['backed_up'] as $path) {
            $this->place($backup.'/files/'.$path, $this->base().'/'.$path);
        }
        if ($meta['vendor'] && is_dir($backup.'/vendor')) {
            $failed = $this->base().'/.vendor-failed-'.$meta['id'];
            if (is_dir($this->base().'/vendor') && ! rename($this->base().'/vendor', $failed)) {
                throw new RuntimeException('Cannot move the new vendor/ aside');
            }
            if (! rename($backup.'/vendor', $this->base().'/vendor')) {
                throw new RuntimeException('Cannot put the old vendor/ back');
            }
            File::deleteDirectory($failed);
        }
        if (is_file($backup.'/installed.json')) {
            copy($backup.'/installed.json', $this->manifest->installedPath());
        }

        $this->writeMeta($backup, ['rolled_back' => true] + $meta);
    }

    private function afterRestore(array $meta, bool $wasDown): void
    {
        $this->artisan->run(['package:discover']);
        $this->artisan->run(['optimize:clear']);
        if ($meta['config_cached'] ?? false) {
            $this->artisan->run(['optimize']);
        }
        if (! $wasDown) {
            $this->artisan->run(['up']);
        }
    }

    /**
     * Which core files the release replaces, adds and removes. Unchanged files are left alone.
     *
     * @return array{replace: list<string>, add: list<string>, remove: list<string>}
     */
    private function plan(array $release): array
    {
        $current = $this->manifest->hashes();
        $plan = ['replace' => [], 'add' => [], 'remove' => []];

        foreach ($release['files'] as $path => $hash) {
            if (! isset($current[$path])) {
                $plan['add'][] = $path;
            } elseif ($current[$path] !== $hash) {
                $plan['replace'][] = $path;
            }
        }
        foreach (array_keys($current) as $path) {
            if (! isset($release['files'][$path]) && ! CoreManifest::matches($path, CoreManifest::SITE_OWNED)) {
                $plan['remove'][] = $path;
            }
        }

        return $plan;
    }

    private function needsDependencies(array $release): bool
    {
        $deps = $release['artifacts']['dependencies'] ?? null;
        $lock = $this->base().'/composer.lock';

        return is_array($deps) && ($deps['lock_sha256'] ?? null) !== (is_file($lock) ? hash_file('sha256', $lock) : null);
    }

    /** Copy into place through a temporary file and a rename, so no request sees half a file. */
    private function place(string $source, string $target): void
    {
        File::ensureDirectoryExists(dirname($target));
        $tmp = $target.'.core-update-tmp';
        if (! copy($source, $tmp) || ! rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('Cannot write '.substr($target, strlen($this->base()) + 1));
        }
    }

    private function extract(string $zipFile, string $to): void
    {
        File::deleteDirectory($to);
        $zip = new ZipArchive;
        if ($zip->open($zipFile, ZipArchive::RDONLY) !== true || ! $zip->extractTo($to)) {
            throw new RuntimeException('Cannot unpack '.basename($zipFile));
        }
        $zip->close();
    }

    private function writable(string $path): bool
    {
        if (file_exists($path)) {
            return is_writable($path) && is_writable(dirname($path));
        }
        while (! file_exists($path) && $path !== dirname($path)) {
            $path = dirname($path);
        }

        return is_writable($path);
    }

    private function latestBackup(): ?array
    {
        $dirs = glob($this->coreDir().'/backups/*', GLOB_ONLYDIR) ?: [];
        rsort($dirs, SORT_STRING);
        foreach ($dirs as $dir) {
            $meta = json_decode((string) @file_get_contents($dir.'/meta.json'), true);
            if (is_array($meta) && ! ($meta['rolled_back'] ?? false)) {
                return $meta + ['dir' => $dir];
            }
        }

        return null;
    }

    private function pruneBackups(): void
    {
        $dirs = glob($this->coreDir().'/backups/*', GLOB_ONLYDIR) ?: [];
        rsort($dirs, SORT_STRING);
        foreach (array_slice($dirs, self::KEEP_BACKUPS) as $dir) {
            File::deleteDirectory($dir);
        }
    }

    private function writeMeta(string $backup, array $meta): void
    {
        unset($meta['dir']);
        file_put_contents($backup.'/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function writeState(array $state): void
    {
        File::ensureDirectoryExists($this->coreDir());
        $state = array_merge($this->state(), $state, ['updated_at' => now()->toIso8601String()]);
        file_put_contents($this->coreDir().'/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function history(array $entry): void
    {
        File::ensureDirectoryExists($this->coreDir());
        file_put_contents($this->coreDir().'/history.jsonl', json_encode(['at' => now()->toIso8601String()] + $entry, JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
    }

    private function acquireLock(): bool
    {
        File::ensureDirectoryExists($this->coreDir());
        $handle = fopen($this->coreDir().'/update.lock', 'c');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle) {
                fclose($handle);
            }

            return false;
        }
        $this->lock = $handle;

        return true;
    }

    private function releaseLock(): void
    {
        if ($this->lock) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
            $this->lock = null;
        }
    }

    private function isLocked(): bool
    {
        if (! is_file($this->coreDir().'/update.lock')) {
            return false;
        }
        $handle = fopen($this->coreDir().'/update.lock', 'c');
        if ($handle === false) {
            return false;
        }
        $free = flock($handle, LOCK_EX | LOCK_NB);
        if ($free) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return ! $free;
    }

    private function isDown(): bool
    {
        return is_file($this->base().'/storage/framework/down');
    }

    private function coreDir(): string
    {
        return $this->base().'/storage/app/core';
    }

    private function base(): string
    {
        return $this->manifest->base();
    }
}
