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
