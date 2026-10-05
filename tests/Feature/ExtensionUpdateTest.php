<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Support\CoreManifest;
use App\Support\CoreUpdate\CoreUpdater;
use App\Support\CoreUpdate\ReleaseBuilder;
use App\Support\CoreUpdate\ReleaseSigner;
use App\Support\CoreUpdate\ReleaseVerifier;
use App\Support\Extensions\ExtensionManager;
use App\Support\Extensions\ExtensionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\BuildsExtensions;
use Tests\Support\FakeArtisan;
use Tests\TestCase;

/**
 * Core updates on a website with extensions (docs/EXTENSIONS_DESIGN.md, section 2): an update that
 * an enabled extension does not support is refused before anything changes, extensions/ is never
 * written, and core:health makes sure the extensions that worked still load afterwards.
 */
class ExtensionUpdateTest extends TestCase
{
    use BuildsExtensions;
    use RefreshDatabase;

    private string $src;

    private string $out;

    private array $key;

    private FakeArtisan $artisan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeExtensionSite('1.0.0');
        $this->src = sys_get_temp_dir().'/ext-upd-src-'.uniqid();
        $this->out = sys_get_temp_dir().'/ext-upd-out-'.uniqid();
        $common = ['composer.json' => '{"require":{"php":"^8.2"}}', 'composer.lock' => '{"lock":"A"}', 'vendor/autoload.php' => 'vendor A'];
        $this->write($this->extensionSite, $common + ['app/Models/Product.php' => 'product v1']);
        $this->write($this->src, $common + ['VERSION' => '1.1.0', 'app/Models/Product.php' => 'product v2']);

        $this->key = ReleaseSigner::generate();
        config(['core_update.public_keys' => [$this->key['public']], 'core_update.min_from' => '1.0.0']);
        (new CoreManifest($this->extensionSite))->recordInstalled();
        $this->artisan = new FakeArtisan;
    }

    protected function tearDown(): void
    {
        $this->removeExtensionSite();
        File::deleteDirectory($this->src);
        File::deleteDirectory($this->out);

        parent::tearDown();
    }

    public function test_an_update_an_extension_does_not_support_is_blocked_until_it_is_disabled(): void
    {
        $this->makeExtension('dealers', ['name' => 'Quản lý đại lý', 'version' => '1.4.0', 'requires_core' => '>=1.0.0 <1.1.0']);
        $release = $this->release('1.1.0');
        $before = file_get_contents($this->extensionSite.'/app/Models/Product.php');

        $result = $this->updater()->apply($release);

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['blockers']);
        $this->assertSame(
            'extensions: Quản lý đại lý (dealers) 1.4.0 needs core >=1.0.0 <1.1.0, this update is 1.1.0 — update the extension or run `php artisan extension:disable dealers`',
            $result['blockers'][0],
        );
        $this->assertSame($before, file_get_contents($this->extensionSite.'/app/Models/Product.php'), 'nothing changed');
        $this->assertSame([], $this->artisan->calls, 'no maintenance, no artisan');
        $state = $this->updater()->state();
        $this->assertSame(['blocked', '1.1.0'], [$state['status'], $state['to']]);
        $this->assertStringContainsString('extension:disable dealers', $state['error']);

        // Same release, once the extension is switched off.
        (new ExtensionState($this->extensionSite))->disable('dealers', 'chờ bản mới');
        $result = $this->updater()->apply($release);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame('installed', $this->updater()->state()['status']);
        $this->assertContains('core:health', $this->artisan->calls, 'a disabled extension is not health-checked');
    }

    public function test_updates_a_to_b_to_c_keep_extensions_untouched_and_health_checked(): void
    {
        $this->makeExtension('dealers', ['requires_core' => '>=1.0.0 <2.0.0'], files: ['views/index.blade.php' => 'mine']);
        $this->makeExtension('misa', ['requires_core' => '^1.0']);
        $extensionFiles = $this->extensionFiles();

        $this->assertTrue($this->updater()->apply($this->release('1.1.0'))['ok']);
        $this->assertContains('core:health --extensions=dealers,misa', $this->artisan->calls);

        $this->artisan = new FakeArtisan;
        $this->write($this->src, ['app/Models/Product.php' => 'product v3']);
        $result = $this->updater()->apply($this->release('1.2.0'));

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame('1.2.0', trim(file_get_contents($this->extensionSite.'/VERSION')));
        $this->assertSame('product v3', file_get_contents($this->extensionSite.'/app/Models/Product.php'));
        $this->assertSame($extensionFiles, $this->extensionFiles(), 'extensions/ never written by an update');
        $this->assertContains('core:health --extensions=dealers,misa', $this->artisan->calls);
    }

    public function test_an_extension_already_failing_does_not_block_every_update(): void
    {
        $this->makeExtension('dealers', ['requires_core' => '^1.0']);
        $this->makeExtension('misa', ['requires_core' => '^1.0']);
        $running = new ExtensionManager($this->extensionSite, new ExtensionState($this->extensionSite));
        $running->markFailed('misa', 'broken before the update');
        $this->app->instance(ExtensionManager::class, $running);

        $result = $this->updater()->apply($this->release('1.1.0'));
        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertContains('core:health --extensions=dealers', $this->artisan->calls);
    }

    public function test_core_health_fails_when_an_extension_that_worked_no_longer_loads(): void
    {
        $this->makeExtensionSite('1.2.0');
        $ok = $this->uniqueExtensionId('o');
        $broken = $this->uniqueExtensionId('b');
        $this->makeExtension($ok);
        $this->makeExtension($broken, boot: "throw new \\RuntimeException('incompatible with the new core');");
        $this->loadExtensions();

        $this->artisan('core:health', ['--extensions' => $ok])->assertExitCode(0);
        $this->artisan('core:health', ['--extensions' => "{$ok},{$broken}"])
            ->expectsOutputToContain("extension {$broken} did not load (failed")
            ->assertExitCode(1);
        $this->artisan('core:health', ['--extensions' => 'gone'])
            ->expectsOutputToContain('extension gone did not load (missing)')
            ->assertExitCode(1);
    }

    public function test_extensions_are_website_files_a_release_may_never_carry(): void
    {
        $this->assertSame('site', (new CoreManifest($this->extensionSite))->owner('extensions/dealers/src/DealersServiceProvider.php'));
        $this->assertContains('extensions/**', CoreManifest::SITE_OWNED);
    }

    public function test_the_update_page_says_why_it_was_blocked(): void
    {
        $this->app->instance(CoreManifest::class, new CoreManifest($this->extensionSite));
        File::ensureDirectoryExists($this->extensionSite.'/storage/app/core');
        file_put_contents($this->extensionSite.'/storage/app/core/state.json', json_encode([
            'status' => 'blocked', 'from' => '1.0.0', 'to' => '2.0.0', 'step' => 'check', 'updated_at' => now()->toIso8601String(),
            'error' => 'extensions: Quản lý đại lý (dealers) 1.4.0 needs core >=1.0.0 <2.0.0, this update is 2.0.0',
        ]));
        $role = Role::query()->create(['name' => 'Superadmin', 'permissions' => ['*'], 'is_system' => true]);
        $superadmin = \App\Models\User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($superadmin)->get(route('admin.core-update.index', ['locale' => 'vi']))
            ->assertOk()->assertSee('Bị chặn')->assertSee('Quản lý đại lý (dealers) 1.4.0 needs core');
    }

    private function updater(): CoreUpdater
    {
        return new CoreUpdater(new CoreManifest($this->extensionSite), app(ReleaseVerifier::class), $this->artisan);
    }

    private function release(string $version): string
    {
        $this->write($this->src, ['VERSION' => $version]);
        $out = $this->out.'/'.uniqid();
        (new ReleaseBuilder($this->src))->build($out, ['channel' => 'stable', 'private_pem' => $this->key['private_pem']]);

        return $out;
    }

    /** @return array<string, string> */
    private function extensionFiles(): array
    {
        $files = [];
        foreach (File::allFiles($this->extensionSite.'/extensions') as $file) {
            $files[str_replace('\\', '/', $file->getRelativePathname())] = sha1_file($file->getPathname());
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
