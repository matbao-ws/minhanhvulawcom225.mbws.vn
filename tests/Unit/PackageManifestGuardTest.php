<?php

namespace Tests\Unit;

use App\Support\PackageManifestGuard;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * The 1.4.0-beta.4 failure: a website deployed with dev packages in vendor/ got a vendor/ without
 * them, and the cached manifest still naming Laravel\Pail stopped package:discover itself.
 */
class PackageManifestGuardTest extends TestCase
{
    private string $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = sys_get_temp_dir().'/package-manifest-guard-'.uniqid();
        mkdir($this->cache);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->cache);

        parent::tearDown();
    }

    private function manifest(array $providers): void
    {
        file_put_contents($this->cache.'/packages.php', '<?php return '.var_export([
            'laravel/sanctum' => ['providers' => ['Laravel\Sanctum\SanctumServiceProvider']],
            'some/package' => ['providers' => $providers],
        ], true).';');
        file_put_contents($this->cache.'/services.php', '<?php return [];');
    }

    public function test_a_manifest_naming_a_provider_vendor_no_longer_has_is_removed(): void
    {
        $this->manifest(['Laravel\Pail\GoneServiceProvider']);

        $this->assertTrue(PackageManifestGuard::clearIfStale($this->cache));
        $this->assertFileDoesNotExist($this->cache.'/packages.php');
        $this->assertFileDoesNotExist($this->cache.'/services.php');
    }

    public function test_a_manifest_whose_providers_all_exist_is_kept(): void
    {
        $this->manifest(['Illuminate\Support\ServiceProvider']);

        $this->assertFalse(PackageManifestGuard::clearIfStale($this->cache));
        $this->assertFileExists($this->cache.'/packages.php');
        $this->assertFileExists($this->cache.'/services.php');
    }

    public function test_an_unreadable_manifest_is_removed_and_a_missing_one_is_fine(): void
    {
        file_put_contents($this->cache.'/packages.php', '<?php return "not an array";');
        $this->assertTrue(PackageManifestGuard::clearIfStale($this->cache));

        $this->assertFalse(PackageManifestGuard::clearIfStale($this->cache));
    }

    public function test_checking_does_not_load_the_providers(): void
    {
        $this->manifest(['Illuminate\Foundation\Providers\ConsoleSupportServiceProvider']);
        $loaded = class_exists('Illuminate\Foundation\Providers\ConsoleSupportServiceProvider', false);

        PackageManifestGuard::clearIfStale($this->cache);

        $this->assertSame($loaded, class_exists('Illuminate\Foundation\Providers\ConsoleSupportServiceProvider', false));
    }

    public function test_bootstrap_runs_the_guard_before_the_application_is_configured(): void
    {
        $bootstrap = (string) file_get_contents(dirname(__DIR__, 2).'/bootstrap/app.php');

        $this->assertLessThan(
            strpos($bootstrap, 'return Application::configure('),
            strpos($bootstrap, "PackageManifestGuard::clearIfStale(__DIR__.'/cache');"),
        );
    }
}
