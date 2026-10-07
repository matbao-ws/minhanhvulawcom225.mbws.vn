<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AiGateway\GatewayClient;
use App\Support\CoreManifest;
use App\Support\CoreUpdate\CoreUpdater;
use App\Support\CoreUpdate\CoreUpdateService;
use App\Support\CoreUpdate\ReleaseBuilder;
use App\Support\CoreUpdate\ReleaseDownloader;
use App\Support\CoreUpdate\ReleaseSigner;
use App\Support\CoreUpdate\ReleaseVerifier;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeArtisan;
use Tests\TestCase;

/**
 * The website side of the update service: asking the gateway, the admin page queueing work for
 * cron, running it, and reporting back.
 */
class CoreUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    private string $release;

    private FakeArtisan $artisan;

    private array $reports = [];

    private ?array $offer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $id = uniqid();
        $this->site = sys_get_temp_dir().'/core-svc-site-'.$id;
        $src = sys_get_temp_dir().'/core-svc-src-'.$id;
        $common = ['composer.json' => '{"require":{"php":"^8.2"}}', 'composer.lock' => '{"lock":"A"}', 'vendor/autoload.php' => 'vendor'];
        $this->write($this->site, $common + ['VERSION' => '1.0.0', 'app/A.php' => 'v1']);
        $this->write($src, $common + ['VERSION' => '1.0.1', 'app/A.php' => 'v2']);

        $key = ReleaseSigner::generate();
        config([
            'core_update.public_keys' => [$key['public']],
            'core_update.min_from' => '1.0.0',
            'core_update.enabled' => true,
            'ai_gateway.url' => 'https://gw.test',
            'ai_gateway.token' => 'mbp_project_token',
        ]);
        $this->release = sys_get_temp_dir().'/core-svc-rel-'.$id;
        (new ReleaseBuilder($src))->build($this->release, ['channel' => 'stable', 'private_pem' => $key['private_pem']]);
        File::deleteDirectory($src);
        (new CoreManifest($this->site))->recordInstalled();

        $this->artisan = new FakeArtisan;
        $manifest = new CoreManifest($this->site);
        $updater = new CoreUpdater($manifest, app(ReleaseVerifier::class), $this->artisan);
        $this->app->instance(CoreManifest::class, $manifest);
        $this->app->instance(CoreUpdater::class, $updater);
        $this->app->instance(CoreUpdateService::class, new CoreUpdateService(new GatewayClient, $updater, new ReleaseDownloader, $manifest));

        $this->offer = $this->offerFor('1.0.1');
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/v1/core/update') {
                return Http::response(['ok' => true, 'update' => $this->offer]);
            }
            if ($path === '/v1/core/report') {
                $this->reports[] = $request->data();

                return Http::response(['ok' => true]);
            }
            if (str_starts_with($path, '/v1/core/releases/1.0.1/') && is_file($this->release.'/'.basename($path))) {
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

    public function test_poll_only_shows_an_offer_unless_the_gateway_marks_it_auto(): void
    {
        $this->artisan('core:poll')->expectsOutputToContain('1.0.1 (patch)')->assertExitCode(0);
        $this->assertSame('1.0.0', $this->version());
        $this->assertSame('1.0.1', app(CoreUpdateService::class)->available()['update']['version']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/core/update?version=1.0.0&php=') && $r->hasHeader('Authorization', 'Bearer mbp_project_token'));

        $this->offer['auto'] = true;
        $this->artisan('core:poll')->expectsOutputToContain('UPDATE_INSTALLED 1.0.1')->assertExitCode(0);
        $this->assertSame('1.0.1', $this->version());
        $this->assertSame('installed', end($this->reports)['event']);
        $this->assertSame(['1.0.0', '1.0.1'], [end($this->reports)['from'], end($this->reports)['to']]);
    }

    public function test_an_admin_click_is_queued_and_cron_runs_it(): void
    {
        $admin = $this->superadmin();
        app(CoreUpdateService::class)->offer();

        $this->actingAs($admin)->get(route('admin.core-update.index', ['locale' => 'vi']))
            ->assertOk()->assertSee('1.0.0')->assertSee('Cập nhật lên 1.0.1');

        $this->actingAs($admin)->post(route('admin.core-update.update', ['locale' => 'vi']), ['version' => '1.0.1'])
            ->assertSessionHas('success');
        $this->assertSame('1.0.0', $this->version(), 'nothing installs in the web request');
        $this->assertSame('update', app(CoreUpdateService::class)->requested()['action']);

        $this->artisan('core:run-requested')->expectsOutput('DONE')->assertExitCode(0);
        $this->assertSame('1.0.1', $this->version());
        $this->assertNull(app(CoreUpdateService::class)->requested());
        $this->artisan('core:run-requested')->assertExitCode(0); // nothing queued: nothing happens

        $this->actingAs($admin)->post(route('admin.core-update.rollback', ['locale' => 'vi']))->assertSessionHas('success');
        $this->artisan('core:run-requested')->expectsOutput('DONE')->assertExitCode(0);
        $this->assertSame('1.0.0', $this->version());
        $this->assertSame('rolled_back', end($this->reports)['event']);
    }

    public function test_a_release_paused_after_the_click_is_not_installed(): void
    {
        app(CoreUpdateService::class)->offer();
        app(CoreUpdateService::class)->request(['action' => 'update', 'version' => '1.0.1']);
        $this->offer = null;

        $this->artisan('core:run-requested')->expectsOutputToContain('no longer offered')->assertExitCode(1);
        $this->assertSame('1.0.0', $this->version());
        $this->assertNull(app(CoreUpdateService::class)->requested(), 'not retried every minute');
    }

    public function test_a_failed_install_is_reported_with_its_step(): void
    {
        $this->artisan->failOn = 'migrate';
        $result = app(CoreUpdateService::class)->run($this->offer);

        $this->assertTrue($result['rolled_back']);
        $this->assertSame(['failed', 'migrate', '1.0.1'], [end($this->reports)['event'], end($this->reports)['step'], end($this->reports)['to']]);
        $this->assertSame('1.0.0', $this->version());
    }

    public function test_the_page_needs_a_superadmin_and_a_backup_confirmation_when_asked(): void
    {
        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['settings.view', 'settings.update']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]))
            ->get(route('admin.core-update.index', ['locale' => 'vi']))->assertForbidden();

        $this->offer['database']['backup_required'] = true;
        $admin = $this->superadmin();
        $this->actingAs($admin)->post(route('admin.core-update.check', ['locale' => 'vi']))->assertSessionHas('success', 'Có bản 1.0.1.');

        $this->actingAs($admin)->post(route('admin.core-update.update', ['locale' => 'vi']), ['version' => '1.0.1'])->assertSessionHas('error');
        $this->assertNull(app(CoreUpdateService::class)->requested());
        $this->actingAs($admin)->post(route('admin.core-update.update', ['locale' => 'vi']), ['version' => '9.9.9', 'backup_done' => 1])->assertSessionHas('error');

        $this->actingAs($admin)->post(route('admin.core-update.update', ['locale' => 'vi']), ['version' => '1.0.1', 'backup_done' => 1])->assertSessionHas('success');
        $this->assertTrue(app(CoreUpdateService::class)->requested()['backup_done']);
    }

    public function test_an_http_package_url_on_the_gateway_host_is_downloaded_over_https(): void
    {
        // A gateway behind a TLS proxy once sent http:// URLs: following the redirect to https://
        // the HTTP client dropped the token and every download got a 401.
        $this->offer['base_url'] = 'http://gw.test/v1/core/releases/1.0.1';
        $service = app(CoreUpdateService::class);
        $this->assertSame('https://gw.test/v1/core/releases/1.0.1', $service->packageUrl($this->offer['base_url']));
        $this->assertSame('http://cdn.other/x', $service->packageUrl('http://cdn.other/x'), 'another host is left alone');

        $this->assertTrue($service->run($this->offer)['ok']);
        Http::assertSent(fn ($r) => $r->url() === 'https://gw.test/v1/core/releases/1.0.1/release.json'
            && $r->hasHeader('Authorization', 'Bearer mbp_project_token'));
        Http::assertNotSent(fn ($r) => str_starts_with($r->url(), 'http://gw.test/v1/core/releases'));
    }

    public function test_the_page_shows_what_is_new_and_escapes_html_in_the_notes(): void
    {
        $admin = $this->superadmin();
        $this->offer['notes'] = "## Tính năng mới\n- **GHN** tạo vận đơn tự động.\n- <script>alert(1)</script> [x](javascript:alert(2))";
        $this->actingAs($admin)->post(route('admin.core-update.check', ['locale' => 'vi']));

        $page = $this->actingAs($admin)->get(route('admin.core-update.index', ['locale' => 'vi']))->assertOk();
        $page->assertSee('Có gì mới trong bản 1.0.1')->assertSee('<strong>GHN</strong>', false)->assertSee('Tính năng mới');
        // Raw HTML stays text; an unsafe link never becomes an <a> (the sidebar has its own javascript: links).
        preg_match('/<div class="release-notes[^"]*"[^>]*>(.*?)<\/div>/s', $page->getContent(), $notes);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $notes[1]);
        $this->assertStringNotContainsString('<script>', $notes[1]);
        $this->assertStringNotContainsString('<a ', $notes[1]);

        unset($this->offer['notes']);
        $this->actingAs($admin)->post(route('admin.core-update.check', ['locale' => 'vi']));
        $this->actingAs($admin)->get(route('admin.core-update.index', ['locale' => 'vi']))
            ->assertSee('Bản này chưa có ghi chú phát hành.');
    }

    public function test_progress_is_queued_then_running_then_idle_and_survives_a_reload(): void
    {
        $admin = $this->superadmin();
        $service = app(CoreUpdateService::class);
        $service->offer();
        $this->assertSame('idle', $service->progress()['phase']);

        $this->actingAs($admin)->post(route('admin.core-update.update', ['locale' => 'vi']), ['version' => '1.0.1']);
        $queued = $this->actingAs($admin)->getJson(route('admin.core-update.status', ['locale' => 'vi']))->assertOk()->json();
        $this->assertSame(['queued', 'update', '1.0.1', false], [$queued['phase'], $queued['action'], $queued['target'], $queued['stale']]);
        $this->assertNotNull($queued['requested_at']);

        // A reload shows the same progress, from the server's times.
        $this->actingAs($admin)->get(route('admin.core-update.index', ['locale' => 'vi']))->assertOk()
            ->assertSee('data-phase="queued"', false)->assertSee('Đang cập nhật lên bản 1.0.1')->assertSee('Tải bản cập nhật');

        // While cron runs it — download included — the page sees "running".
        $seen = null;
        Http::fake(function ($request) use (&$seen) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/release.json')) {
                $seen = app(CoreUpdateService::class)->progress();
            }
            if ($path === '/v1/core/update') {
                return Http::response(['ok' => true, 'update' => $this->offer]);
            }
            if (str_starts_with($path, '/v1/core/releases/1.0.1/') && is_file($this->release.'/'.basename($path))) {
                return Http::response(file_get_contents($this->release.'/'.basename($path)));
            }

            return Http::response(['ok' => true]);
        });
        $this->artisan('core:run-requested')->expectsOutput('DONE')->assertExitCode(0);
        $this->assertSame(['running', 'download', 'download'], [$seen['phase'], $seen['step'], $seen['step_group']]);

        $done = $service->progress();
        $this->assertSame(['idle', 'installed', '1.0.1'], [$done['phase'], $done['last']['status'], $done['version']]);
        $this->assertFileDoesNotExist($this->site.'/storage/app/core/running.json');
    }

    public function test_a_request_waiting_too_long_is_flagged(): void
    {
        $this->travelTo(now()->subMinutes(6), fn () => app(CoreUpdateService::class)->request(['action' => 'update', 'version' => '1.0.1']));

        $this->assertTrue(app(CoreUpdateService::class)->progress()['stale']);
    }

    public function test_failures_before_installing_are_recorded_and_explained(): void
    {
        $admin = $this->superadmin();
        $service = app(CoreUpdateService::class);

        // Release withdrawn between the click and cron.
        $service->request(['action' => 'update', 'version' => '9.9.9']);
        $this->artisan('core:run-requested')->assertExitCode(1);
        $this->assertSame(['failed', 'offer'], [app(CoreUpdater::class)->state()['status'], app(CoreUpdater::class)->state()['step']]);

        // Download refused (1.1.0 first failed with a 401): the gateway has no package to serve.
        File::deleteDirectory($this->release);
        $service->request(['action' => 'update', 'version' => '1.0.1']);
        $this->artisan('core:run-requested')->assertExitCode(1);

        $state = app(CoreUpdater::class)->state();
        $this->assertSame(['failed', 'download'], [$state['status'], $state['step']]);
        $this->assertMatchesRegularExpression('/^UPD-\d{14}-[A-Z0-9]{4}$/', $state['ref']);
        $this->actingAs($admin)->get(route('admin.core-update.index', ['locale' => 'vi']))->assertOk()
            ->assertSee('Không tải được bản cập nhật. Thử lại sau ít phút.')->assertSee('Mã: DOWNLOAD')
            ->assertSee('Mã tra cứu: '.$state['ref'])->assertDontSee('Download failed');
        // Nor in the JSON the page polls.
        $status = $this->actingAs($admin)->getJson(route('admin.core-update.status', ['locale' => 'vi']))->json();
        $this->assertStringNotContainsString('Download failed', json_encode($status));
        $this->assertSame($state['ref'], $status['last']['ref']);
    }

    public function test_reasons_become_plain_sentences_with_a_code(): void
    {
        $problems = \App\Support\CoreUpdate\UpdateProblems::explain(
            'drift: 3 core file(s) edited | extensions: Quản lý đại lý (dealers) 1.4.0 needs core <2 — run `php artisan extension:disable dealers` | migrate: artisan migrate failed: x | something odd'
        );

        $this->assertSame(['DRIFT', 'EXTENSIONS', 'INSTALL_FAILED', 'UNKNOWN'], array_column($problems, 'code'));
        $this->assertSame('Cài đặt gặp lỗi ở bước cập nhật dữ liệu; website đã tự quay về bản đang chạy.', $problems[2]['message']);
        $this->assertStringNotContainsString('php artisan', implode(' ', array_column($problems, 'message')));
        $this->assertSame([], \App\Support\CoreUpdate\UpdateProblems::explain(null));
    }

    public function test_the_page_shows_no_server_command_and_records_a_missing_baseline(): void
    {
        $admin = $this->superadmin();
        @unlink($this->site.'/storage/app/core/installed.json');

        $page = $this->actingAs($admin)->get(route('admin.core-update.index', ['locale' => 'vi']))->assertOk();
        $page->assertSee('Chưa sẵn sàng cập nhật')->assertSee('Ghi mốc ngay')->assertDontSee('php artisan')->assertDontSee('CORE_UPDATE_ENABLED');

        $this->actingAs($admin)->post(route('admin.core-update.baseline', ['locale' => 'vi']))->assertSessionHas('success');
        $this->assertFileExists($this->site.'/storage/app/core/installed.json');
        // Never over an existing record: that would hide files edited by hand.
        $this->actingAs($admin)->post(route('admin.core-update.baseline', ['locale' => 'vi']))->assertSessionHas('error');

        $editor = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Ed', 'permissions' => ['settings.update']])->id]);
        $this->actingAs($editor)->post(route('admin.core-update.baseline', ['locale' => 'vi']))->assertForbidden();
        $this->actingAs($editor)->getJson(route('admin.core-update.status', ['locale' => 'vi']))->assertForbidden();
    }

    public function test_disabled_websites_neither_poll_nor_run_requests(): void
    {
        config(['core_update.enabled' => false]);
        app(CoreUpdateService::class)->request(['action' => 'update', 'version' => '1.0.1']);

        $this->artisan('core:poll')->expectsOutputToContain('disabled')->assertExitCode(0);
        $this->artisan('core:run-requested')->assertExitCode(0);
        Http::assertNothingSent();
        $this->assertSame('1.0.0', $this->version());
    }

    public function test_the_fleet_polls_at_spread_minutes(): void
    {
        $events = collect(app(Schedule::class)->events());
        $poll = $events->first(fn ($e) => str_contains((string) $e->command, 'core:poll'));
        $this->assertSame(CoreUpdateService::pollMinute().' * * * *', $poll->expression);
        $this->assertNotNull($events->first(fn ($e) => str_contains((string) $e->command, 'core:run-requested') && $e->expression === '* * * * *'));

        config(['app.url' => 'https://a.example']);
        $a = CoreUpdateService::pollMinute();
        config(['app.url' => 'https://b.example']);
        $this->assertNotSame($a, CoreUpdateService::pollMinute());
    }

    // ---------------------------------------------------------------------

    private function offerFor(string $version): array
    {
        return [
            'version' => $version, 'channel' => 'stable', 'level' => 'patch', 'auto' => false,
            'requires' => ['php' => '^8.2', 'min_from' => '1.0.0'],
            'database' => ['migration' => false, 'rollback_safe' => true, 'backup_required' => false],
            'base_url' => 'https://gw.test/v1/core/releases/'.$version,
        ];
    }

    private function version(): string
    {
        return trim(file_get_contents($this->site.'/VERSION'));
    }

    private function superadmin(): User
    {
        $role = Role::query()->create(['name' => 'Superadmin', 'permissions' => ['*'], 'is_system' => true]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function write(string $base, array $files): void
    {
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($base.'/'.$path));
            file_put_contents($base.'/'.$path, $contents);
        }
    }
}
