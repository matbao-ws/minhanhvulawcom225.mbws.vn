<?php

namespace Tests\Feature;

use App\Support\CoreManifest;
use App\Support\CoreUpdate\CoreUpdater;
use App\Support\CoreUpdate\ReleaseBuilder;
use App\Support\CoreUpdate\ReleaseDownloader;
use App\Support\CoreUpdate\ReleaseSigner;
use App\Support\CoreUpdate\ReleaseVerifier;
use App\Support\CoreUpdate\Semver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeArtisan;
use Tests\TestCase;

/**
 * The updater on a website: what it refuses to start, what it changes, and that every failure
 * leaves the previous code in place — theme and website files untouched throughout.
 */
class CoreUpdaterTest extends TestCase
{
    private string $site;

    private string $src;

    private string $out;

    private array $key;

    private FakeArtisan $artisan;

    protected function setUp(): void
    {
        parent::setUp();

        $id = uniqid();
        $this->site = sys_get_temp_dir().'/core-upd-site-'.$id;
        $this->src = sys_get_temp_dir().'/core-upd-src-'.$id;
        $this->out = sys_get_temp_dir().'/core-upd-out-'.$id;

        $common = [
            'composer.json' => '{"require":{"php":"^8.2"}}',
            'composer.lock' => '{"lock":"A"}',
            'config/app.php' => '<?php return [];',
            'vendor/autoload.php' => 'vendor A',
        ];
        $this->write($this->site, $common + [
            'VERSION' => '1.0.0',
            'app/Models/Product.php' => 'product v1',
            'app/Old.php' => 'old',
            'resources/views/client/home.blade.php' => 'theme home',
            'routes/theme.php' => 'theme routes',
            '.env' => 'APP_KEY=secret',
        ]);
        $this->write($this->src, $common + [
            'VERSION' => '1.1.0',
            'app/Models/Product.php' => 'product v2',
            'app/New.php' => 'new',
        ]);

        $this->key = ReleaseSigner::generate();
        config(['core_update.public_keys' => [$this->key['public']], 'core_update.min_from' => '1.0.0']);
        (new CoreManifest($this->site))->recordInstalled();
        $this->artisan = new FakeArtisan;
    }

    protected function tearDown(): void
    {
        foreach ([$this->site, $this->src, $this->out] as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    public function test_a_valid_release_is_ready(): void
    {
        $this->assertSame([], $this->updater()->check($this->release())['blockers']);
    }

    public function test_preflight_blocks_with_a_reason(): void
    {
        $this->assertBlocked('not_newer', $this->release(['VERSION' => '1.0.0']));

        config(['core_update.min_from' => '1.0.5']);
        $this->assertBlocked('min_from', $this->release());
        config(['core_update.min_from' => '1.0.0']);

        $this->assertBlocked('php: needs PHP >=99', $this->release(['composer.json' => '{"require":{"php":">=99"}}']));

        $this->assertBlocked('backup_required', $release = $this->release([], ['backup_required' => true]));
        $this->assertSame([], $this->updater()->check($release, ['backup_done' => true])['blockers']);

        $release = $this->release(['composer.lock' => '{"lock":"B"}']);
        $this->assertSame([], $this->updater()->check($release)['blockers']);
        $deps = json_decode(file_get_contents($release.'/release.json'), true)['artifacts']['dependencies']['file'];
        rename($release.'/'.$deps, $this->out.'/'.$deps);
        $this->assertBlocked('dependencies_missing', $release);

        file_put_contents($this->site.'/app/Models/Product.php', 'hand edit');
        $this->assertBlocked('drift: 1 core file(s) edited on this website (app/Models/Product.php)', $this->release());

        unlink($this->site.'/storage/app/core/installed.json');
        $this->assertBlocked('no_baseline', $this->release());

        $this->assertBlocked('invalid_release', $this->out.'/missing');
    }

    public function test_install_replaces_adds_and_removes_core_files_only(): void
    {
        $result = $this->updater()->apply($this->release());

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame('product v2', file_get_contents($this->site.'/app/Models/Product.php'));
        $this->assertSame('new', file_get_contents($this->site.'/app/New.php'));
        $this->assertFileDoesNotExist($this->site.'/app/Old.php');
        $this->assertSame('1.1.0', trim(file_get_contents($this->site.'/VERSION')));

        $this->assertSame('theme home', file_get_contents($this->site.'/resources/views/client/home.blade.php'));
        $this->assertSame('theme routes', file_get_contents($this->site.'/routes/theme.php'));
        $this->assertSame('APP_KEY=secret', file_get_contents($this->site.'/.env'));
        $this->assertSame('vendor A', file_get_contents($this->site.'/vendor/autoload.php'), 'same composer.lock: vendor/ untouched');

        $manifest = new CoreManifest($this->site);
        $this->assertSame('1.1.0', $manifest->installed()['version']);
        $this->assertSame(['modified' => [], 'missing' => [], 'added' => []], $manifest->drift());

        $this->assertSame(['down --retry=60', 'package:discover', 'migrate --force', 'optimize:clear', 'core:health', 'up'], $this->artisan->calls);
        $this->assertSame('installed', $this->updater()->state()['status']);
        $this->assertStringContainsString('"event":"installed"', file_get_contents($this->site.'/storage/app/core/history.jsonl'));

        $this->assertBlocked('not_newer', $this->release(), 'the same release twice');
    }

    public function test_a_failure_after_the_swap_restores_the_previous_code(): void
    {
        $before = $this->snapshot();
        $this->artisan->failOn = 'migrate';

        $result = $this->updater()->apply($this->release());

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['rolled_back']);
        $this->assertStringStartsWith('migrate:', $result['error']);
        $this->assertSame($before, $this->snapshot(), 'every file back as it was');
        $this->assertSame('1.0.0', (new CoreManifest($this->site))->installed()['version']);
        $this->assertSame('up', end($this->artisan->calls), 'the site comes out of maintenance');
        $this->assertNotContains('migrate:rollback', $this->artisan->calls, 'the database is never rolled back');
        $this->assertSame('failed', $this->updater()->state()['status']);

        $this->artisan = new FakeArtisan;
        $this->assertTrue($this->updater()->apply($this->release())['ok'], 'a later attempt can still succeed');
    }

    public function test_a_new_composer_lock_swaps_vendor_and_a_failure_puts_the_old_one_back(): void
    {
        $release = $this->release(['composer.lock' => '{"lock":"B"}', 'vendor/autoload.php' => 'vendor B']);

        $this->artisan->failOn = 'core:health';
        $this->assertTrue($this->updater()->apply($release)['rolled_back']);
        $this->assertSame('vendor A', file_get_contents($this->site.'/vendor/autoload.php'));
        $this->assertSame('{"lock":"A"}', file_get_contents($this->site.'/composer.lock'));

        $this->artisan = new FakeArtisan;
        $this->assertTrue($this->updater()->apply($release)['ok']);
        $this->assertSame('vendor B', file_get_contents($this->site.'/vendor/autoload.php'));
    }

    public function test_swapping_vendor_drops_the_package_manifest_built_from_the_old_one(): void
    {
        // A manifest of the old vendor/ (here naming a dev package the new one lacks) is read before
        // package:discover can rebuild it, so it must be gone by the time that command runs.
        $caches = ['bootstrap/cache/packages.php' => '<?php return ["laravel/pail" => []];', 'bootstrap/cache/services.php' => '<?php return [];'];
        $this->write($this->site, $caches);

        $this->assertTrue($this->updater()->apply($this->release())['ok']);
        $this->assertFileExists($this->site.'/bootstrap/cache/packages.php', 'same composer.lock: manifest kept');

        $this->artisan = new FakeArtisan;
        $this->assertTrue($this->updater()->apply($this->release(['VERSION' => '1.2.0', 'composer.lock' => '{"lock":"B"}']))['ok']);
        $this->assertFileDoesNotExist($this->site.'/bootstrap/cache/packages.php');
        $this->assertFileDoesNotExist($this->site.'/bootstrap/cache/services.php');
    }

    public function test_putting_the_old_vendor_back_drops_the_manifest_of_the_new_one(): void
    {
        $this->write($this->site, ['bootstrap/cache/packages.php' => '<?php return [];']);
        $this->artisan->failOn = 'core:health';

        $this->assertTrue($this->updater()->apply($this->release(['composer.lock' => '{"lock":"B"}']))['rolled_back']);
        $this->assertFileDoesNotExist($this->site.'/bootstrap/cache/packages.php');
        $this->assertSame('vendor A', file_get_contents($this->site.'/vendor/autoload.php'));
    }

    public function test_rollback_puts_back_the_code_of_the_last_update_once(): void
    {
        $before = $this->snapshot();
        $this->assertTrue($this->updater()->apply($this->release())['ok']);

        $result = $this->updater()->rollback();

        $this->assertTrue($result['ok']);
        $this->assertSame('1.0.0', $result['restored']);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame('1.0.0', (new CoreManifest($this->site))->installed()['version']);
        $this->assertSame('No update to roll back.', $this->updater()->rollback()['error']);
    }

    public function test_an_interrupted_update_blocks_the_next_one_until_rolled_back(): void
    {
        $before = $this->snapshot();
        $this->assertTrue($this->updater()->apply($this->release())['ok']);
        // As if the process died after the swap: state still "running", nobody holds the lock.
        $state = $this->site.'/storage/app/core/state.json';
        file_put_contents($state, json_encode(['status' => 'running', 'step' => 'migrate'] + json_decode(file_get_contents($state), true)));

        $this->assertBlocked('interrupted', $this->release(['VERSION' => '1.2.0']));

        $this->artisan->calls = [];
        $this->assertTrue($this->updater()->rollback()['ok']);
        $this->assertSame($before, $this->snapshot());
        $this->assertContains('up', $this->artisan->calls);
    }

    public function test_a_site_already_in_maintenance_stays_there(): void
    {
        $this->write($this->site, ['storage/framework/down' => '{}']);

        $this->assertTrue($this->updater()->apply($this->release())['ok']);
        $this->assertNotContains('up', $this->artisan->calls);
        $this->assertNotContains('down --retry=60', $this->artisan->calls);
    }

    public function test_only_one_update_runs_at_a_time(): void
    {
        $release = $this->release();
        File::ensureDirectoryExists($this->site.'/storage/app/core');
        $handle = fopen($this->site.'/storage/app/core/update.lock', 'c');
        flock($handle, LOCK_EX);

        try {
            $result = $this->updater()->apply($release);
            $this->assertFalse($result['ok']);
            $this->assertStringStartsWith('locked', $result['blockers'][0]);
            $this->assertSame([], $this->artisan->calls);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function test_the_downloader_skips_the_dependencies_package_when_composer_lock_matches(): void
    {
        $release = $this->release(['composer.lock' => '{"lock":"B"}']);
        $json = json_decode(file_get_contents($release.'/release.json'), true);
        $deps = $json['artifacts']['dependencies'];

        Http::fake(function ($request) use ($release) {
            $file = basename(parse_url($request->url(), PHP_URL_PATH));

            return is_file($release.'/'.$file) ? Http::response(file_get_contents($release.'/'.$file)) : Http::response('', 404);
        });

        $target = $this->out.'/dl-same';
        (new ReleaseDownloader)->download('https://releases.test/1.1.0/', $target, 'tok', $deps['lock_sha256']);
        $this->assertFileExists($target.'/core-1.1.0.zip');
        $this->assertFileDoesNotExist($target.'/'.$deps['file']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tok'));

        $target = $this->out.'/dl-new';
        (new ReleaseDownloader)->download('https://releases.test/1.1.0', $target, null, hash('sha256', '{"lock":"A"}'));
        $this->assertFileExists($target.'/'.$deps['file']);
        $this->assertSame('1.1.0', app(ReleaseVerifier::class)->verify($target)['version']);
    }

    public function test_the_command_reports_ready_blocked_and_installed(): void
    {
        $this->app->instance(CoreUpdater::class, $this->updater());
        $release = $this->release();

        $this->artisan('core:update', ['--from' => $release, '--check' => true])->expectsOutput('UPDATE_READY 1.1.0')->assertExitCode(0);
        $this->artisan('core:update', ['--from' => $release])->expectsOutput('UPDATE_INSTALLED 1.1.0')->assertExitCode(0);
        $this->artisan('core:update', ['--from' => $release, '--check' => true])->expectsOutput('UPDATE_BLOCKED 1.1.0')->assertExitCode(1);
        $this->artisan('core:rollback')->expectsOutputToContain('restored to 1.0.0')->assertExitCode(0);
    }

    public function test_php_constraints(): void
    {
        foreach ([['8.2.28', '^8.2', true], ['8.3.1', '^8.2', true], ['9.0.0', '^8.2', false], ['8.1.9', '^8.2', false],
            ['8.2.1', '~8.2.0', true], ['8.3.0', '~8.2.0', false], ['8.4.0', '>=8.2 <9.0', true], ['8.1.0', '^7.4 || ^8.2', false],
            ['7.4.3', '^7.4 || ^8.2', true], ['8.3.4', '8.3.*', true], ['8.2.4', '8.3.*', false], ['8.2.0', 'weird', false]] as [$v, $c, $ok]) {
            $this->assertSame($ok, Semver::satisfies($v, $c), "{$v} vs {$c}");
        }
        $this->assertSame(-1, Semver::compare('1.1.0-beta.1', '1.1.0'));
        $this->assertSame(1, Semver::compare('1.10.0', '1.9.0'));
    }

    // ---------------------------------------------------------------------

    private function updater(): CoreUpdater
    {
        return new CoreUpdater(new CoreManifest($this->site), app(ReleaseVerifier::class), $this->artisan);
    }

    /** Build a release from the source tree with some files changed. */
    private function release(array $files = [], array $options = []): string
    {
        $src = $this->src.'-'.uniqid();
        File::copyDirectory($this->src, $src);
        $this->write($src, $files);
        $out = $this->out.'/'.uniqid();

        try {
            (new ReleaseBuilder($src))->build($out, $options + ['channel' => 'stable', 'private_pem' => $this->key['private_pem']]);
        } finally {
            File::deleteDirectory($src);
        }

        return $out;
    }

    private function assertBlocked(string $reason, string $release, string $message = ''): void
    {
        $blockers = $this->updater()->check($release)['blockers'];
        $this->assertNotEmpty(array_filter($blockers, fn ($b) => str_starts_with($b, $reason)), $message.' got: '.json_encode($blockers));
    }

    /** Every file of the site outside storage/, with its content hash. */
    private function snapshot(): array
    {
        $files = [];
        foreach (File::allFiles($this->site, true) as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            if (! str_starts_with($path, 'storage/')) {
                $files[$path] = sha1_file($file->getPathname());
            }
        }
        ksort($files);

        return $files;
    }

    private function write(string $base, array $files): void
    {
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($base.'/'.$path));
            file_put_contents($base.'/'.$path, $contents);
        }
    }
}
