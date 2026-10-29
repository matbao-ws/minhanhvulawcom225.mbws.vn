<?php

namespace App\Support\CoreUpdate;

use App\Support\CoreManifest;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Puts hand-edited core files back from the website's own release, so its updates run again.
 * Asked from the MatBao console only (GatewayCommandController → CoreUpdateService::requestRestore,
 * carried out by `core:run-requested`); there is deliberately no button in the website's admin.
 *
 * The package is the signed release of the version the website already runs, verified like an
 * update. Every file it replaces or removes is first copied to
 * storage/app/core/drift-backups/<time>/, so an edit can still be moved into the theme or an
 * extension. Only paths that differ are touched; nothing of the website's own files is.
 */
class CoreRestorer
{
    public function __construct(
        private readonly CoreManifest $manifest,
        private readonly ReleaseVerifier $verifier,
        private readonly ArtisanRunner $artisan,
    ) {}

    /**
     * @param  string  $dir  the downloaded release of the installed version
     * @return array{ok: bool, files?: int, backup?: ?string, error?: string}
     */
    public function restore(string $dir): array
    {
        try {
            $release = $this->verifier->verify($dir);
        } catch (ReleaseInvalid $e) {
            return ['ok' => false, 'error' => 'invalid_release: '.$e->getMessage()];
        }
        if ($release['version'] !== $this->manifest->version()) {
            return ['ok' => false, 'error' => "version: the package is {$release['version']}, this website runs {$this->manifest->version()}"];
        }

        $drift = $this->manifest->drift();
        if ($drift === null) {
            return ['ok' => false, 'error' => 'no_record: this website never recorded its installed release'];
        }
        $put = array_merge($drift['modified'], $drift['missing']);
        $remove = $drift['added'];
        if ($put === [] && $remove === []) {
            return ['ok' => true, 'files' => 0, 'backup' => null];
        }

        $base = $this->manifest->base();
        $stamp = now()->format('Ymd-His');
        $extracted = $base.'/storage/app/core/restore-'.$stamp;
        $backup = $base.'/storage/app/core/drift-backups/'.$stamp;
        $configCached = is_file($base.'/bootstrap/cache/config.php');

        try {
            $this->extract($dir.'/'.$release['artifacts']['core']['file'], $extracted);

            foreach ($put as $path) {
                if (! isset($release['files'][$path])) {
                    // Recorded as core at install but not in this release: leave it to an update.
                    continue;
                }
                $this->keep($base, $backup, $path);
                $this->place($extracted.'/'.$path, $base.'/'.$path);
            }
            foreach ($remove as $path) {
                if (isset($release['files'][$path])) {
                    $this->keep($base, $backup, $path);
                    $this->place($extracted.'/'.$path, $base.'/'.$path);

                    continue;
                }
                $this->keep($base, $backup, $path);
                @unlink($base.'/'.$path);
            }

            if (is_dir($backup)) {
                File::put($backup.'/README.txt', "File core bị sửa trên website, đã được thay bằng bản gốc {$release['version']} lúc {$stamp}.\n"
                    ."Lấy lại đoạn cần giữ và đưa vào theme (resources/views/client, routes/theme.php) hoặc extension.\n\n"
                    .implode("\n", array_merge(array_map(fn ($p) => "khôi phục: {$p}", $put), array_map(fn ($p) => "gỡ: {$p}", $remove)))."\n");
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'restore: '.$e->getMessage()];
        } finally {
            File::deleteDirectory($extracted);
        }

        // Restored config or routes: no stale cache may keep the edited version alive.
        $this->artisan->run(['optimize:clear']);
        if ($configCached) {
            $this->artisan->run(['optimize']);
        }

        $left = $this->manifest->drift() ?? ['modified' => [], 'missing' => [], 'added' => []];
        $remaining = array_merge($left['modified'], $left['missing'], $left['added']);
        if ($remaining !== []) {
            return ['ok' => false, 'error' => 'restore: still different: '.implode(', ', array_slice($remaining, 0, 5)), 'backup' => $this->relative($backup)];
        }

        return ['ok' => true, 'files' => count($put) + count($remove), 'backup' => is_dir($backup) ? $this->relative($backup) : null];
    }

    private function keep(string $base, string $backup, string $path): void
    {
        if (is_file($base.'/'.$path)) {
            File::ensureDirectoryExists(dirname($backup.'/'.$path));
            if (! copy($base.'/'.$path, $backup.'/'.$path)) {
                throw new RuntimeException("Cannot back up {$path}");
            }
        }
    }

    private function place(string $source, string $target): void
    {
        File::ensureDirectoryExists(dirname($target));
        $tmp = $target.'.core-restore-tmp';
        if (! copy($source, $tmp) || ! rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('Cannot write '.substr($target, strlen($this->manifest->base()) + 1));
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

    private function relative(string $path): string
    {
        return substr($path, strlen($this->manifest->base()) + 1);
    }
}
