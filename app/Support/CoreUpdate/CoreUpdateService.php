<?php

namespace App\Support\CoreUpdate;

use App\Services\AiGateway\GatewayClient;
use App\Services\AiGateway\GatewayUnavailable;
use App\Support\CoreManifest;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * This website and the update service on the gateway:
 *
 *   offer()     ask the gateway which release this website should install (kept in available.json)
 *   request()   an admin clicked "Update" or "Roll back": write requested.json — never install from a
 *               web request; cron (`core:run-requested`, every minute) picks it up
 *   run()       download through the gateway, install with CoreUpdater, report back
 *
 * The gateway decides who gets which release when (channel, staged rollout, pin, pause); the
 * website still verifies every package itself (ReleaseVerifier) and can always refuse.
 */
class CoreUpdateService
{
    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly CoreUpdater $updater,
        private readonly ReleaseDownloader $downloader,
        private readonly CoreManifest $manifest,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('core_update.enabled') && $this->gateway->configured();
    }

    /**
     * @return array<string, mixed>|null the offered release, null when up to date
     *
     * @throws GatewayUnavailable
     */
    public function offer(): ?array
    {
        $update = $this->gateway->get('v1/core/update', ['version' => $this->manifest->version(), 'php' => PHP_VERSION])['update'] ?? null;
        $this->write('available.json', ['checked_at' => now()->toIso8601String(), 'for_version' => $this->manifest->version(), 'update' => $update]);

        return is_array($update) ? $update : null;
    }

    /** @return array{checked_at?: string, update?: ?array} the last answer, if it is still about this version */
    public function available(): array
    {
        $data = $this->read('available.json');

        return ($data['for_version'] ?? null) === $this->manifest->version() ? $data : [];
    }

    /** @param array{action: string, version?: string, backup_done?: bool, by?: string} $request */
    public function request(array $request): void
    {
        $this->write('requested.json', $request + ['requested_at' => now()->toIso8601String()]);
    }

    public function requested(): ?array
    {
        return $this->read('requested.json') ?: null;
    }

    /**
     * Carry out what an admin asked for. Taken off the queue first, so a failing request is not
     * retried every minute.
     *
     * @return array<string, mixed>|null null when nothing was asked
     */
    public function runRequested(): ?array
    {
        $request = $this->requested();
        if ($request === null) {
            return null;
        }
        @unlink($this->path('requested.json'));
        // From now until it ends — download included — the update page shows the run as going on.
        $this->write('running.json', $request + ['started_at' => now()->toIso8601String()]);

        try {
            return $this->carryOut($request);
        } finally {
            @unlink($this->path('running.json'));
        }
    }

    /**
     * Where the admin's request stands, for the update page (which polls it): queued for cron,
     * running (and at which step), or idle with the last run's outcome. Times come from the server,
     * so a reload or another browser shows the same progress.
     *
     * @return array<string, mixed>
     */
    public function progress(): array
    {
        $requested = $this->requested();
        $running = $this->read('running.json') ?: null;
        $state = $this->updater->state();

        $phase = match (true) {
            $running !== null => 'running',
            $requested !== null => 'queued',
            default => 'idle',
        };
        $since = $running['started_at'] ?? $requested['requested_at'] ?? null;
        $step = $phase !== 'running' ? null
            : (($state['status'] ?? null) === 'running' ? (string) ($state['step'] ?? '') : 'download');

        return [
            'phase' => $phase,
            'action' => ($running ?? $requested)['action'] ?? null,
            'target' => ($running ?? $requested)['version'] ?? null,
            'requested_at' => $running['requested_at'] ?? $requested['requested_at'] ?? null,
            'started_at' => $running['started_at'] ?? null,
            'step' => $step,
            'step_group' => $step === null ? null : UpdateProblems::stepGroup($step),
            // Cron runs at the top of each minute: past a few minutes something is wrong with it.
            'stale' => $since !== null && now()->diffInMinutes($since, true) >= ($phase === 'running' ? 30 : 5),
            'version' => $this->manifest->version(),
            // No error text: pages (and this JSON) show the reference, the log holds the detail.
            'last' => array_intersect_key($state, array_flip(['status', 'from', 'to', 'ref', 'updated_at'])),
            'now' => now()->toIso8601String(),
        ];
    }

    /** @param  array<string, mixed>  $request */
    private function carryOut(array $request): array
    {
        if (($request['action'] ?? null) === 'rollback') {
            $from = $this->manifest->version();
            $result = $this->updater->rollback();
            $this->report($result['ok']
                ? ['event' => 'rolled_back', 'from' => $from, 'to' => $result['restored']]
                : ['event' => 'failed', 'from' => $from, 'step' => 'rollback', 'error' => $result['error']]);

            return $result;
        }

        // Ask again: the release may have been paused since the click.
        $from = $this->manifest->version();
        try {
            $offer = $this->offer();
        } catch (GatewayUnavailable $e) {
            $this->updater->recordFailure($from, $request['version'] ?? null, 'gateway', 'gateway: '.$e->reason);

            return ['ok' => false, 'error' => 'gateway: '.$e->reason];
        }
        if ($offer === null || $offer['version'] !== ($request['version'] ?? null)) {
            $error = 'not_offered: release '.($request['version'] ?? '?').' is no longer offered to this website';
            $this->updater->recordFailure($from, $request['version'] ?? null, 'offer', $error);

            return ['ok' => false, 'error' => $error];
        }

        return $this->run($offer, (bool) ($request['backup_done'] ?? false));
    }

    /**
     * Download, install, report.
     *
     * @param  array<string, mixed>  $offer  as returned by offer()
     * @return array<string, mixed>
     */
    public function run(array $offer, bool $backupDone = false): array
    {
        $from = $this->manifest->version();
        $dir = $this->manifest->base().'/storage/app/core/downloads/'.preg_replace('/[^0-9A-Za-z.-]/', '', (string) $offer['version']);

        try {
            $lock = $this->manifest->base().'/composer.lock';
            $this->downloader->download($this->packageUrl((string) $offer['base_url']), $dir, (string) config('ai_gateway.token'),
                is_file($lock) ? hash_file('sha256', $lock) : null);

            $result = $this->updater->apply($dir, ['backup_done' => $backupDone]);
        } catch (Throwable $e) {
            $result = ['ok' => false, 'from' => $from, 'to' => $offer['version'], 'error' => 'download: '.$e->getMessage()];
            $this->updater->recordFailure($from, (string) $offer['version'], 'download', $result['error']);
        } finally {
            File::deleteDirectory($dir);
        }

        $this->report(match (true) {
            $result['ok'] => ['event' => 'installed', 'from' => $from, 'to' => $result['to']],
            isset($result['blockers']) => ['event' => 'blocked', 'from' => $from, 'to' => $offer['version'], 'error' => implode(' | ', $result['blockers'])],
            default => ['event' => 'failed', 'from' => $from, 'to' => $offer['version'], 'step' => $this->updater->state()['step'] ?? null, 'error' => $result['error'] ?? null],
        });

        return $result;
    }

    /**
     * The gateway's package URL, upgraded to https when it names the gateway's own host over plain
     * http. Following an http → https redirect, the HTTP client drops the token on the port change
     * and the gateway answers 401 — which is how 1.1.0 first failed to download.
     */
    public function packageUrl(string $url): string
    {
        $gateway = parse_url((string) config('ai_gateway.url'));
        $package = parse_url($url);

        if (($package['scheme'] ?? null) === 'http' && ($gateway['scheme'] ?? null) === 'https'
            && isset($package['host'], $gateway['host']) && strcasecmp($package['host'], $gateway['host']) === 0) {
            return 'https'.substr($url, 4);
        }

        return $url;
    }

    /** Best effort: an unreachable gateway never fails an update. */
    public function report(array $report): void
    {
        try {
            $this->gateway->post('v1/core/report', [], $report + ['version' => $this->manifest->version(), 'php' => PHP_VERSION]);
        } catch (GatewayUnavailable) {
            // The next poll reports the running version anyway.
        }
    }

    /** Minute of the hour this website polls at: spread over the hour, the same every hour. */
    public static function pollMinute(): int
    {
        return crc32((string) config('app.url')) % 60;
    }

    private function path(string $file): string
    {
        return $this->manifest->base().'/storage/app/core/'.$file;
    }

    private function read(string $file): array
    {
        $path = $this->path($file);

        return is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
    }

    private function write(string $file, array $data): void
    {
        File::ensureDirectoryExists(dirname($this->path($file)));
        file_put_contents($this->path($file), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
