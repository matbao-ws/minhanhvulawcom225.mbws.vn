<?php

namespace Tests\Feature;

use App\Support\CoreUpdate\ReleaseBuilder;
use App\Support\CoreUpdate\ReleaseInvalid;
use App\Support\CoreUpdate\ReleaseSigner;
use App\Support\CoreUpdate\ReleaseVerifier;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * Signed release packages: what goes in, and every way a package that must not be installed is
 * caught before it touches a website.
 */
class CoreReleaseTest extends TestCase
{
    private string $site;

    private string $out;

    private array $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = sys_get_temp_dir().'/core-release-site-'.uniqid();
        $this->out = sys_get_temp_dir().'/core-release-out-'.uniqid();
        foreach ([
            'VERSION' => "1.4.0\n",
            'composer.json' => '{"require":{"php":"^8.2"}}',
            'composer.lock' => '{"packages":[]}',
            'artisan' => '<?php',
            'app/Models/Product.php' => '<?php // product',
            'database/migrations/2026_01_01_000000_create_products.php' => '<?php // create',
            'resources/views/core/editor.blade.php' => 'editor',
            'resources/views/client/layouts/app.blade.php' => 'theme layout',
            'routes/theme.php' => '<?php',
            '.env' => 'APP_KEY=secret',
            'vendor/autoload.php' => '<?php // autoload',
            'vendor/laravel/framework/src/Foundation/Application.php' => '<?php',
        ] as $path => $contents) {
            $this->putFile($path, $contents);
        }

        $this->key = ReleaseSigner::generate();
        config(['core_update.public_keys' => [$this->key['public']], 'core_update.min_from' => '1.0.0']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->site);
        File::deleteDirectory($this->out);
        putenv('CORE_RELEASE_SIGNING_KEY');

        parent::tearDown();
    }

    public function test_a_release_holds_core_files_and_dependencies_and_verifies(): void
    {
        $release = $this->build();

        $this->assertSame('1.4.0', $release['version']);
        $this->assertSame('beta', $release['channel']);
        $this->assertSame(['php' => '^8.2', 'min_from' => '1.0.0'], $release['requires']);
        $this->assertSame($this->key['key_id'], $release['key_id']);
        $this->assertSame(['VERSION', 'app/Models/Product.php', 'artisan', 'composer.json', 'composer.lock',
            'database/migrations/2026_01_01_000000_create_products.php', 'resources/views/core/editor.blade.php'], array_keys($release['files']));

        $this->assertSame($this->zipNames($this->out.'/core-1.4.0.zip'), array_keys($release['files']));
        $deps = $release['artifacts']['dependencies'];
        $this->assertSame('dependencies-'.substr(hash('sha256', '{"packages":[]}'), 0, 16).'.zip', $deps['file']);
        $this->assertSame(['vendor/autoload.php', 'vendor/laravel/framework/src/Foundation/Application.php'], $this->zipNames($this->out.'/'.$deps['file']));

        $this->assertSame('1.4.0', $this->verifier()->verify($this->out)['version']);

        // The dependencies artifact is optional on a website whose composer.lock already matches.
        unlink($this->out.'/'.$deps['file']);
        $this->assertSame('1.4.0', $this->verifier()->verify($this->out)['version']);
    }

    public function test_database_impact_comes_from_new_and_contract_migrations(): void
    {
        $first = $this->build();
        $this->assertTrue($first['database']['migration']);
        $this->assertTrue($first['database']['rollback_safe']);

        $this->assertFalse($this->build(['previous' => $first])['database']['migration'], 'nothing new since the previous release');

        $this->putFile('database/migrations/2026_11_01_000000_add_subtitle.php', '<?php // expand');
        $expand = $this->build(['previous' => $first])['database'];
        $this->assertSame(['2026_11_01_000000_add_subtitle.php'], $expand['new_migrations']);
        $this->assertTrue($expand['rollback_safe']);
        $this->assertFalse($expand['backup_required']);

        $this->putFile('database/migrations/2026_12_01_000000_drop_legacy.php', "<?php // @contract-migration: legacy unused since 1.3\n");
        $contract = $this->build(['previous' => $first])['database'];
        $this->assertSame(['2026_12_01_000000_drop_legacy.php'], $contract['contract_migrations']);
        $this->assertFalse($contract['rollback_safe']);
        $this->assertTrue($contract['backup_required']);
    }

    public function test_tampering_or_an_untrusted_key_is_rejected(): void
    {
        $this->build();
        $json = file_get_contents($this->out.'/release.json');

        file_put_contents($this->out.'/release.json', str_replace('"1.4.0"', '"1.4.1"', $json));
        $this->assertRejected('signature does not match');
        file_put_contents($this->out.'/release.json', $json);

        config(['core_update.public_keys' => [ReleaseSigner::generate()['public']]]);
        $this->assertRejected('signature does not match');
        config(['core_update.public_keys' => []]);
        $this->assertRejected('No trusted signing key');
        config(['core_update.public_keys' => [$this->key['public']]]);

        file_put_contents($this->out.'/core-1.4.0.zip', 'x', FILE_APPEND);
        $this->assertRejected('Artifact corrupted');
    }

    /** Signed with the right key yet still wrong: a build mistake must not reach websites either. */
    public function test_a_signed_package_with_a_theme_file_an_unlisted_file_or_a_bad_path_is_rejected(): void
    {
        $this->resign(fn (array &$r) => $r['files']['resources/views/client/layouts/app.blade.php'] = hash('sha256', 'theme layout'));
        $this->assertRejected('Not a core file: resources/views/client/layouts/app.blade.php');

        $this->resign(function (array &$r) {
            $r['core_paths'][] = 'resources/views/client/**';
            $r['files']['resources/views/client/layouts/app.blade.php'] = hash('sha256', 'theme layout');
        });
        $this->assertRejected('Not a core file', 'a release cannot claim a website path through its own allowlist');

        $this->resign(fn (array &$r) => $r['files']['.env'] = hash('sha256', 'x'));
        $this->assertRejected('Not a core file: .env');

        $this->resign(fn (array &$r) => null, ['app/Extra.php' => '<?php']);
        $this->assertRejected('unlisted file: app/Extra.php');

        $this->resign(fn (array &$r) => null, ['app/../../evil.php' => '<?php']);
        $this->assertRejected('Unsafe path');

        $this->resign(fn (array &$r) => $r['files']['app/Models/Product.php'] = hash('sha256', 'other'));
        $this->assertRejected('Checksum mismatch: app/Models/Product.php');
    }

    public function test_a_new_core_directory_in_the_release_is_accepted(): void
    {
        $this->putFile('modules/Blog/Post.php', '<?php');
        $this->resign(function (array &$r) {
            $r['core_paths'][] = 'modules/**';
            $r['files']['modules/Blog/Post.php'] = hash('sha256', '<?php');
        }, ['modules/Blog/Post.php' => '<?php']);

        $this->assertArrayHasKey('modules/Blog/Post.php', $this->verifier()->verify($this->out)['files']);
    }

    public function test_release_command_needs_a_trusted_key_and_never_prints_it(): void
    {
        $this->artisan('core:release', ['--output' => $this->out])
            ->expectsOutputToContain('signing key is not set')->assertExitCode(1);

        putenv('CORE_RELEASE_SIGNING_KEY='.ReleaseSigner::generate()['private_pem']);
        $this->artisan('core:release', ['--output' => $this->out])
            ->expectsOutputToContain('not in config/core_update.php')->assertExitCode(1);

        putenv('CORE_RELEASE_SIGNING_KEY='.$this->key['private_pem']);
        $this->artisan('core:release', ['--output' => $this->out, '--channel' => 'stable', '--no-dependencies' => true])
            ->doesntExpectOutputToContain('PRIVATE KEY')
            ->assertExitCode(0);

        $this->assertFileExists($this->out.'/release.json.sig');
        $this->artisan('core:verify-release', ['dir' => $this->out])->expectsOutputToContain('is valid')->assertExitCode(0);
    }

    public function test_keygen_writes_the_private_key_outside_the_repository_only(): void
    {
        $this->artisan('core:keygen', ['--private' => base_path('signing.pem')])
            ->expectsOutputToContain('must not be written inside the repository')->assertExitCode(1);
        $this->assertFileDoesNotExist(base_path('signing.pem'));

        $target = $this->out.'/signing.pem';
        File::ensureDirectoryExists($this->out);
        $this->artisan('core:keygen', ['--private' => $target])
            ->doesntExpectOutputToContain('PRIVATE KEY')
            ->expectsOutputToContain('public_keys')
            ->assertExitCode(0);

        $pem = file_get_contents($target);
        $this->assertStringContainsString('PRIVATE KEY', $pem);
        $this->assertTrue(ReleaseSigner::verify('x', ReleaseSigner::sign('x', $pem), ReleaseSigner::publicOf($pem)));

        $this->artisan('core:keygen', ['--private' => $target])->expectsOutputToContain('already exists')->assertExitCode(1);
    }

    private function build(array $options = []): array
    {
        File::deleteDirectory($this->out);

        return (new ReleaseBuilder($this->site))->build($this->out, $options + ['channel' => 'beta', 'private_pem' => $this->key['private_pem']]);
    }

    /**
     * Rebuild, change release.json (and optionally add zip entries), then sign it again with the
     * trusted key — a package that is authentic but wrong.
     */
    private function resign(callable $change, array $extraEntries = []): void
    {
        $release = $this->build();
        $change($release);

        $zipPath = $this->out.'/core-1.4.0.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath);
        foreach ($release['files'] as $path => $hash) {
            if ($zip->locateName($path) === false && is_file($this->site.'/'.$path)) {
                $zip->addFile($this->site.'/'.$path, $path);
            }
        }
        foreach ($extraEntries as $name => $contents) {
            if ($zip->locateName($name) === false) {
                $zip->addFromString($name, $contents);
            }
        }
        $zip->close();
        $release['artifacts']['core']['sha256'] = hash_file('sha256', $zipPath);
        $release['artifacts']['core']['size'] = filesize($zipPath);

        $json = json_encode($release, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->out.'/release.json', $json);
        file_put_contents($this->out.'/release.json.sig', ReleaseSigner::sign($json, $this->key['private_pem']));
    }

    private function assertRejected(string $reason, string $message = ''): void
    {
        try {
            $this->verifier()->verify($this->out);
            $this->fail("Release accepted; expected rejection: {$reason}. {$message}");
        } catch (ReleaseInvalid $e) {
            $this->assertStringContainsString($reason, $e->getMessage(), $message);
        }
    }

    private function verifier(): ReleaseVerifier
    {
        return app(ReleaseVerifier::class);
    }

    private function zipNames(string $path): array
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        sort($names, SORT_STRING);

        return $names;
    }

    private function putFile(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($this->site.'/'.$path));
        file_put_contents($this->site.'/'.$path, $contents);
    }
}
