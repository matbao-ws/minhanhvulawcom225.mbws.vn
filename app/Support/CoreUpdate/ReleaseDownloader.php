<?php

namespace App\Support\CoreUpdate;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches a release into a local directory: release.json, its signature, the core package and —
 * only when this website's composer.lock differs — the dependencies package. Nothing downloaded
 * is trusted; ReleaseVerifier checks it all afterwards.
 */
class ReleaseDownloader
{
    /** @param string $baseUrl where release.json and the artifacts sit side by side */
    public function download(string $baseUrl, string $dir, ?string $token = null, ?string $currentLockHash = null): void
    {
        File::ensureDirectoryExists($dir);
        $baseUrl = rtrim($baseUrl, '/');

        $this->fetch($baseUrl.'/release.json', $dir.'/release.json', $token);
        $this->fetch($baseUrl.'/release.json.sig', $dir.'/release.json.sig', $token);

        // Only to know the artifact names; their content is checked against the signed sha256 later.
        $release = json_decode((string) file_get_contents($dir.'/release.json'), true);
        $core = (string) ($release['artifacts']['core']['file'] ?? '');
        if ($core === '' || $core !== basename($core)) {
            throw new RuntimeException('release.json names no core package.');
        }
        $this->fetch($baseUrl.'/'.rawurlencode($core), $dir.'/'.$core, $token);

        $deps = $release['artifacts']['dependencies'] ?? null;
        if (is_array($deps) && ($deps['lock_sha256'] ?? null) !== $currentLockHash) {
            $file = (string) ($deps['file'] ?? '');
            if ($file === '' || $file !== basename($file)) {
                throw new RuntimeException('release.json names an invalid dependencies package.');
            }
            $this->fetch($baseUrl.'/'.rawurlencode($file), $dir.'/'.$file, $token);
        }
    }

    private function fetch(string $url, string $target, ?string $token): void
    {
        $request = Http::timeout(900)->connectTimeout(15)->retry(2, 2000, throw: false)->withOptions(['sink' => $target]);
        if (filled($token)) {
            $request = $request->withToken($token);
        }

        $response = $request->get($url);
        if (! $response->successful()) {
            @unlink($target);
            throw new RuntimeException("Download failed ({$response->status()}): ".basename($target));
        }
    }
}
