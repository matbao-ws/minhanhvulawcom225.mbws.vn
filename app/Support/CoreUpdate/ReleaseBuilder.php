<?php

namespace App\Support\CoreUpdate;

use App\Support\CoreManifest;
use App\Support\MigrationSafety;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Builds a signed core release from a checkout (in CI, after `composer install --no-dev`):
 *
 *   release.json        what the release is: version, channel, requirements, database impact,
 *                       artifacts with their sha256, and every core file with its sha256
 *   release.json.sig    ECDSA signature over the exact bytes of release.json
 *   core-{version}.zip  the core files (CoreManifest), nothing of a theme or a website
 *   dependencies-{lock}.zip  vendor/, named by the composer.lock hash — a website downloads it
 *                       only when its own lock hash differs
 */
class ReleaseBuilder
{
    public const FORMAT = 1;

    public const CHANNELS = ['dev', 'beta', 'stable'];

    public function __construct(private readonly string $base) {}

    /**
     * @param  array{channel: string, private_pem: string, commit?: ?string, min_from?: ?string, previous?: ?array, backup_required?: bool, dependencies?: bool}  $options
     * @return array the release.json data
     */
    public function build(string $outDir, array $options): array
    {
        $manifest = new CoreManifest($this->base);
        $version = $manifest->version();

        if (! preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', $version)) {
            throw new RuntimeException("VERSION \"{$version}\" is not semver.");
        }
        if (! in_array($options['channel'], self::CHANNELS, true)) {
            throw new RuntimeException('Unknown channel '.$options['channel']);
        }
        if (! is_dir($outDir) && ! mkdir($outDir, 0775, true)) {
            throw new RuntimeException("Cannot create {$outDir}");
        }

        $files = $manifest->hashes();
        $database = $this->database($options['previous'] ?? null, (bool) ($options['backup_required'] ?? false));

        $coreZip = "core-{$version}.zip";
        $this->zip($outDir.'/'.$coreZip, array_keys($files));
        $artifacts = ['core' => $this->artifact($outDir, $coreZip)];

        if ($options['dependencies'] ?? true) {
            $lock = $this->base.'/composer.lock';
            if (! is_file($lock) || ! is_dir($this->base.'/vendor')) {
                throw new RuntimeException('composer.lock and vendor/ are needed for the dependencies artifact.');
            }
            $lockHash = hash_file('sha256', $lock);
            $depsZip = 'dependencies-'.substr($lockHash, 0, 16).'.zip';
            $this->zip($outDir.'/'.$depsZip, $this->vendorFiles());
            $artifacts['dependencies'] = ['lock_sha256' => $lockHash] + $this->artifact($outDir, $depsZip);
        }

        $composer = json_decode((string) file_get_contents($this->base.'/composer.json'), true);
        $public = ReleaseSigner::publicOf($options['private_pem']);

        $release = [
            'format' => self::FORMAT,
            'version' => $version,
            'channel' => $options['channel'],
            'released_at' => now()->toIso8601String(),
            'commit' => $options['commit'] ?? null,
            'key_id' => ReleaseSigner::keyId($public),
            'requires' => [
                'php' => $composer['require']['php'] ?? null,
                'min_from' => $options['min_from'] ?? config('core_update.min_from'),
            ],
            'database' => $database,
            'artifacts' => $artifacts,
            // This version's allowlist: a release may add a core directory older websites don't know.
            'core_paths' => CoreManifest::CORE,
            'files' => $files,
        ];

        $json = json_encode($release, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($outDir.'/release.json', $json);
        file_put_contents($outDir.'/release.json.sig', ReleaseSigner::sign($json, $options['private_pem']));

        return $release;
    }

    /**
     * Whether applying the release migrates, and whether the old code still runs on the migrated
     * schema (rollback_safe): false as soon as one contract migration is in the release.
     */
    private function database(?array $previous, bool $backupRequired): array
    {
        $migrations = array_map('basename', glob($this->base.'/database/migrations/*.php') ?: []);
        sort($migrations);

        $new = $previous !== null
            ? array_values(array_diff($migrations, $previous['database']['migrations'] ?? []))
            : $migrations;
        $contract = array_values(array_filter($new, fn ($m) => MigrationSafety::isContract($this->base.'/database/migrations/'.$m)));

        return [
            'migration' => $new !== [],
            'rollback_safe' => $contract === [],
            'backup_required' => $backupRequired || $contract !== [],
            'new_migrations' => $new,
            'contract_migrations' => $contract,
            'migrations' => $migrations,
        ];
    }

    /** @return list<string> */
    private function vendorFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->base.'/vendor', RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = substr(str_replace('\\', '/', $file->getPathname()), strlen($this->base) + 1);
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    private function zip(string $target, array $paths): void
    {
        @unlink($target);
        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException("Cannot create {$target}");
        }
        foreach ($paths as $path) {
            $zip->addFile($this->base.'/'.$path, $path);
        }
        if (! $zip->close()) {
            throw new RuntimeException("Cannot write {$target}");
        }
    }

    private function artifact(string $dir, string $file): array
    {
        return ['file' => $file, 'sha256' => hash_file('sha256', $dir.'/'.$file), 'size' => filesize($dir.'/'.$file)];
    }
}
