<?php

namespace App\Support\CoreUpdate;

use App\Support\CoreManifest;
use ZipArchive;

/**
 * Checks a downloaded release before anything of it touches a website: signed by a trusted key,
 * artifacts intact, and the core package containing exactly the core files it lists — no theme
 * or website path, no path escaping the site root. The updater (Phase C) runs this first and
 * installs nothing from a release that fails it.
 */
class ReleaseVerifier
{
    /** @param list<string> $publicKeys base64 DER, config('core_update.public_keys') */
    public function __construct(private readonly array $publicKeys) {}

    /**
     * @param  string  $dir  holds release.json, release.json.sig, the core zip and, when downloaded, the dependencies zip
     * @return array the release.json data
     *
     * @throws ReleaseInvalid
     */
    public function verify(string $dir): array
    {
        $json = @file_get_contents($dir.'/release.json');
        $signature = @file_get_contents($dir.'/release.json.sig');
        if ($json === false || $signature === false) {
            throw new ReleaseInvalid('release.json or its signature is missing.');
        }
        if ($this->publicKeys === []) {
            throw new ReleaseInvalid('No trusted signing key is configured (config/core_update.php).');
        }

        $trusted = array_filter($this->publicKeys, fn ($key) => ReleaseSigner::verify($json, $signature, $key));
        if ($trusted === []) {
            throw new ReleaseInvalid('The signature does not match a trusted key.');
        }

        $release = json_decode($json, true);
        if (! is_array($release) || ($release['format'] ?? null) !== ReleaseBuilder::FORMAT) {
            throw new ReleaseInvalid('Unknown release format.');
        }
        if (! preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', (string) ($release['version'] ?? ''))
            || ! in_array($release['channel'] ?? null, ReleaseBuilder::CHANNELS, true)
            || ! is_array($release['files'] ?? null) || ! is_array($release['artifacts']['core'] ?? null)
            || ! is_array($release['core_paths'] ?? null) || array_filter($release['core_paths'], fn ($p) => ! is_string($p)) !== []) {
            throw new ReleaseInvalid('release.json is incomplete.');
        }

        // Core by the release's own allowlist, and never a path this website owns (the hard deny).
        foreach ($release['files'] as $path => $hash) {
            $path = (string) $path;
            if (! self::safePath($path) || ! CoreManifest::matches($path, $release['core_paths'])
                || CoreManifest::matches($path, CoreManifest::SITE_OWNED) || ! preg_match('/^[0-9a-f]{64}$/', (string) $hash)) {
                throw new ReleaseInvalid("Not a core file: {$path}");
            }
        }

        $core = $this->artifact($dir, $release['artifacts']['core']);
        $this->checkZip($core, function (string $name, ZipArchive $zip, int $i) use ($release) {
            if (! array_key_exists($name, $release['files'])) {
                throw new ReleaseInvalid("The core package holds an unlisted file: {$name}");
            }
            if (hash('sha256', (string) $zip->getFromIndex($i)) !== $release['files'][$name]) {
                throw new ReleaseInvalid("Checksum mismatch: {$name}");
            }
        }, count($release['files']));

        $deps = $release['artifacts']['dependencies'] ?? null;
        if (is_array($deps) && is_file($dir.'/'.basename((string) ($deps['file'] ?? '')))) {
            $this->checkZip($this->artifact($dir, $deps), function (string $name) {
                if (! str_starts_with($name, 'vendor/')) {
                    throw new ReleaseInvalid("The dependencies package holds a file outside vendor/: {$name}");
                }
            });
        }

        return $release;
    }

    /** The artifact's local path once its name, size and sha256 check out. */
    private function artifact(string $dir, array $artifact): string
    {
        $file = (string) ($artifact['file'] ?? '');
        if ($file === '' || $file !== basename($file) || ! is_file($dir.'/'.$file)) {
            throw new ReleaseInvalid("Artifact missing: {$file}");
        }
        $path = $dir.'/'.$file;
        if (filesize($path) !== ($artifact['size'] ?? null) || hash_file('sha256', $path) !== ($artifact['sha256'] ?? null)) {
            throw new ReleaseInvalid("Artifact corrupted: {$file}");
        }

        return $path;
    }

    private function checkZip(string $path, callable $check, ?int $expected = null): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new ReleaseInvalid('Cannot open '.basename($path));
        }
        try {
            $seen = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if (! self::safePath($name)) {
                    throw new ReleaseInvalid("Unsafe path in package: {$name}");
                }
                $check($name, $zip, $i);
                $seen++;
            }
            if ($expected !== null && $seen !== $expected) {
                throw new ReleaseInvalid(sprintf('The core package holds %d files, release.json lists %d.', $seen, $expected));
            }
        } finally {
            $zip->close();
        }
    }

    /** Relative, forward slashes, no `..`, no NUL, no drive letter. */
    private static function safePath(string $path): bool
    {
        return $path !== ''
            && ! str_contains($path, "\0")
            && ! str_contains($path, '\\')
            && ! str_starts_with($path, '/')
            && ! preg_match('/^[A-Za-z]:/', $path)
            && ! in_array('..', explode('/', $path), true)
            && ! in_array('.', explode('/', $path), true);
    }
}
