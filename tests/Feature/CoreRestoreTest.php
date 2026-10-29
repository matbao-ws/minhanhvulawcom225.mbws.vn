<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Services\AiGateway\GatewayClient;
use App\Support\CoreManifest;
use App\Support\CoreUpdate\CoreRestorer;
use App\Support\CoreUpdate\CoreUpdater;
use App\Support\CoreUpdate\CoreUpdateService;
use App\Support\CoreUpdate\ReleaseBuilder;
use App\Support\CoreUpdate\ReleaseDownloader;
use App\Support\CoreUpdate\ReleaseSigner;
use App\Support\CoreUpdate\ReleaseVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\FakeArtisan;
use Tests\TestCase;

/**
 * Hand-edited core files reported to the gateway with the update check, and put back from the
 * website's own signed release when the MatBao console asks (never from the website's admin).
 */
class CoreRestoreTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'console-webhook-secret';

    private string $site;

    private string $release;

    private FakeArtisan $artisan;

    private array $reports = [];

    private array $queries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $id = uniqid();
        $this->site = sys_get_temp_dir().'/core-restore-site-'.$id;
        $src = sys_get_temp_dir().'/core-restore-src-'.$id;
        $core = [
            'VERSION' => '1.0.0', 'composer.json' => '{"require":{"php":"^8.2"}}', 'composer.lock' => '{"lock":"A"}',
            'app/A.php' => 'shipped A', 'app/B.php' => 'shipped B', 'routes/web.php' => 'shipped routes', 'vendor/autoload.php' => 'vendor',
        ];
        $this->write($src, $core);
        $this->write($this->site, $core + ['resources/views/client/home.blade.php' => 'theme']);

        $key = ReleaseSigner::generate();
        config([
            'core_update.public_keys' => [$key['public']],
            'core_update.enabled' => true,
            'ai_gateway.url' => 'https://gw.test',
            'ai_gateway.token' => 'mbp_project_token',
            'ai_gateway.webhook_secret' => self::SECRET,
        ]);
        $this->release = sys_get_temp_dir().'/core-restore-rel-'.$id;
        (new ReleaseBuilder($src))->build($this->release, ['channel' => 'stable', 'private_pem' => $key['private_pem']]);
        File::deleteDirectory($src);
        (new CoreManifest($this->site))->recordInstalled();

        $this->artisan = new FakeArtisan;
        $manifest = new CoreManifest($this->site);
        $updater = new CoreUpdater($manifest, app(ReleaseVerifier::class), $this->artisan);
        $restorer = new CoreRestorer($manifest, app(ReleaseVerifier::class), $this->artisan);
        $this->app->instance(CoreManifest::class, $manifest);
        $this->app->instance(CoreUpdater::class, $updater);
        $this->app->instance(CoreUpdateService::class, new CoreUpdateService(new GatewayClient, $updater, new ReleaseDownloader, $manifest, $restorer));

        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/v1/core/update') {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $this->queries[] = $query;

                return Http::response(['ok' => true, 'update' => null]);
            }
            if ($path === '/v1/core/report') {
                $this->reports[] = $request->data();

                return Http::response(['ok' => true]);
            }
            if (str_starts_with($path, '/v1/core/releases/1.0.0/') && is_file($this->release.'/'.basename($path))) {
                return Http::response(file_get_contents($this->release.'/'.basename($path)));
            }

            return Http::response(['ok' => false, 'reason' => 'not_found'], 404);
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->site);
        File::deleteDirectory($this->release);

        parent::tearDown();
    }

    private function editCore(): void
    {
        $this->write($this->site, ['app/A.php' => 'hand edit', 'app/Extra.php' => 'added by hand', 'resources/views/client/home.blade.php' => 'theme edit']);
        unlink($this->site.'/app/B.php');
    }

    private function command(array $body)
    {
        $json = json_encode($body + ['nonce' => Str::random(40)], JSON_UNESCAPED_SLASHES);
        $time = (string) time();

        return $this->call('POST', '/api/gateway/command', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MB_TIMESTAMP' => $time,
            'HTTP_X_MB_SIGNATURE' => hash_hmac('sha256', $time.'.'.$json, self::SECRET),
        ], content: $json);
    }

    public function test_the_update_check_reports_edited_core_files(): void
    {
        $service = app(CoreUpdateService::class);

        $service->offer();
        $this->assertSame('0', $this->queries[0]['drift']);
        $this->assertArrayNotHasKey('drift_files', $this->queries[0]);

        $this->editCore();
        $service->offer();
        $this->assertSame('3', $this->queries[1]['drift']);
        $this->assertSame('m:app/A.php|d:app/B.php|a:app/Extra.php', $this->queries[1]['drift_files']);

        // No record of the installed release: nothing is claimed either way.
        unlink((new CoreManifest($this->site))->installedPath());
        $service->offer();
        $this->assertArrayNotHasKey('drift', $this->queries[2]);
    }

    public function test_the_console_restores_edited_core_files_and_keeps_a_backup(): void
    {
        $this->editCore();

        $this->command(['action' => 'core.restore'])->assertOk()->assertExactJson(['ok' => true, 'queued' => true, 'files' => 3]);
        $this->command(['action' => 'core.restore'])->assertStatus(409)->assertJsonPath('error', 'busy');
        $this->assertSame(1, AdminActivityLog::query()->where('action', 'core.restore_requested_by_gateway')->count());

        $this->artisan('core:run-requested')->assertSuccessful();

        $this->assertSame('shipped A', file_get_contents($this->site.'/app/A.php'));
        $this->assertSame('shipped B', file_get_contents($this->site.'/app/B.php'));
        $this->assertFileDoesNotExist($this->site.'/app/Extra.php');
        $this->assertSame('theme edit', file_get_contents($this->site.'/resources/views/client/home.blade.php'), 'the theme is the website\'s');
        $this->assertSame(['modified' => [], 'missing' => [], 'added' => []], (new CoreManifest($this->site))->drift());

        $backup = glob($this->site.'/storage/app/core/drift-backups/*')[0];
        $this->assertSame('hand edit', file_get_contents($backup.'/app/A.php'));
        $this->assertSame('added by hand', file_get_contents($backup.'/app/Extra.php'));
        $this->assertStringContainsString('khôi phục: app/A.php', file_get_contents($backup.'/README.txt'));
        $this->assertContains('optimize:clear', $this->artisan->calls);

        $report = collect($this->reports)->firstWhere('event', 'restored');
        $this->assertSame('1.0.0', $report['to']);
        $this->assertStringStartsWith('backup: storage/app/core/drift-backups/', $report['error']);
        // The gateway hears at once that nothing differs any more.
        $this->assertSame('0', end($this->queries)['drift']);
        $this->assertDirectoryDoesNotExist($this->site.'/storage/app/core/downloads/restore-1.0.0');
    }

    public function test_nothing_is_queued_when_there_is_nothing_to_restore_or_no_record(): void
    {
        $this->command(['action' => 'core.restore'])->assertUnprocessable()->assertJsonPath('error', 'nothing_to_restore');

        $this->editCore();
        unlink((new CoreManifest($this->site))->installedPath());
        $this->command(['action' => 'core.restore'])->assertUnprocessable()->assertJsonPath('error', 'no_record');

        $this->assertNull(app(CoreUpdateService::class)->requested());
    }

    public function test_a_package_that_fails_verification_changes_nothing_and_blames_no_release(): void
    {
        $this->editCore();
        file_put_contents($this->release.'/release.json.sig', 'forged');

        $this->command(['action' => 'core.restore'])->assertOk();
        $this->artisan('core:run-requested')->assertFailed();

        $this->assertSame('hand edit', file_get_contents($this->site.'/app/A.php'));
        $failed = collect($this->reports)->firstWhere('event', 'failed');
        $this->assertSame('restore', $failed['step']);
        $this->assertStringContainsString('invalid_release', $failed['error']);
        // No `to`: the gateway would count it against the release and could pause it.
        $this->assertArrayNotHasKey('to', $failed);
    }

    public function test_the_websites_admin_has_no_restore_button(): void
    {
        $coreUpdateRoutes = array_filter(array_keys(app('router')->getRoutes()->getRoutesByName()), fn (string $name) => str_starts_with($name, 'admin.core-update.'));

        $this->assertNotEmpty($coreUpdateRoutes);
        $this->assertSame([], array_values(array_filter($coreUpdateRoutes, fn (string $name) => str_contains($name, 'restore'))));
        $this->assertStringNotContainsString('core.restore', (string) file_get_contents(resource_path('views/admin/core-update/index.blade.php')));
    }

    private function write(string $base, array $files): void
    {
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($base.'/'.$path));
            file_put_contents($base.'/'.$path, $contents);
        }
    }
}
