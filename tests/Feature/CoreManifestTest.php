<?php

namespace Tests\Feature;

use App\Support\CoreManifest;
use App\Support\MigrationSafety;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Core/theme ownership (CoreManifest), drift detection (core:status), the expand/contract rule for
 * migrations, and the theme's own routes file — the foundation the core updater stands on.
 */
class CoreManifestTest extends TestCase
{
    private string $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = sys_get_temp_dir().'/core-manifest-'.uniqid();
        foreach ([
            'VERSION' => "1.2.3\n",
            'artisan' => '<?php',
            'app/Models/Product.php' => '<?php // product',
            'config/app.php' => '<?php return [];',
            'routes/client.php' => '<?php',
            'routes/theme.php' => '<?php // the theme',
            'resources/views/core/editor.blade.php' => 'editor',
            'resources/views/client/layouts/app.blade.php' => 'theme layout',
            'public/client-assets/vendor/alpine.min.js' => 'alpine',
            'public/client-assets/css/storefront.css' => 'theme css',
            'public/index.php' => '<?php',
            'vendor/autoload.php' => '<?php',
            '.env' => 'APP_KEY=secret',
            'app/.DS_Store' => 'junk',
        ] as $path => $contents) {
            File::ensureDirectoryExists(dirname($this->site.'/'.$path));
            file_put_contents($this->site.'/'.$path, $contents);
        }

        $this->app->instance(CoreManifest::class, new CoreManifest($this->site));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->site);

        parent::tearDown();
    }

    public function test_only_allowlisted_paths_are_core(): void
    {
        $manifest = new CoreManifest($this->site);

        $this->assertSame('core', $manifest->owner('app/Services/Orders/OrderCreationService.php'));
        $this->assertSame('core', $manifest->owner('resources/views/core/partials/admin-bar.blade.php'));
        $this->assertSame('core', $manifest->owner('public/client-assets/vendor/sortable.min.js'));
        $this->assertSame('core', $manifest->owner('database/migrations/2026_01_01_000000_x.php'));
        $this->assertSame('core', $manifest->owner('VERSION'));
        $this->assertSame('dependencies', $manifest->owner('vendor/laravel/framework/src/x.php'));

        foreach (['resources/views/client/home.blade.php', 'public/client-assets/css/storefront.css', 'routes/theme.php',
            '.env', '.htaccess', 'storage/app/public/a.jpg', 'public/robots.txt', 'public/flaticon.png', 'something-new.txt',
            'application/x.php', 'resources/views/clientele.blade.php'] as $path) {
            $this->assertSame('site', $manifest->owner($path), $path);
        }
    }

    public function test_the_file_list_holds_core_files_only(): void
    {
        $files = (new CoreManifest($this->site))->coreFiles();

        $this->assertSame([
            'VERSION',
            'app/Models/Product.php',
            'artisan',
            'config/app.php',
            'public/client-assets/vendor/alpine.min.js',
            'public/index.php',
            'resources/views/core/editor.blade.php',
            'routes/client.php',
        ], $files);
    }

    public function test_every_file_of_this_repository_in_the_list_is_core(): void
    {
        $manifest = new CoreManifest(base_path());
        $files = $manifest->coreFiles();

        $this->assertContains('resources/views/core/editor.blade.php', $files);
        $this->assertContains('app/Support/CoreManifest.php', $files);
        $this->assertNotContains('.env', $files);
        $this->assertEmpty(array_filter($files, fn ($f) => str_starts_with($f, 'resources/views/client/') || str_starts_with($f, 'vendor/') || str_starts_with($f, 'storage/')));
        $this->assertSame(trim(file_get_contents(base_path('VERSION'))), $manifest->version());
    }

    public function test_manifest_command_prints_versions_and_checksums(): void
    {
        $out = $this->site.'/manifest.json';
        $this->artisan('core:manifest', ['--output' => $out])->assertExitCode(0);

        $json = json_decode(file_get_contents($out), true);
        $this->assertSame('1.2.3', $json['version']);
        $this->assertSame(hash('sha256', '<?php // product'), $json['files']['app/Models/Product.php']);
        $this->assertArrayNotHasKey('routes/theme.php', $json['files']);

        $this->artisan('core:manifest', ['--owner' => 'resources/views/client/x.blade.php'])->expectsOutput('site')->assertExitCode(0);
    }

    public function test_status_needs_a_baseline_then_finds_edited_deleted_and_added_core_files(): void
    {
        $this->artisan('core:status')->assertExitCode(1);

        $this->artisan('core:status', ['--baseline' => true])->assertExitCode(0);
        $this->assertFileExists($this->site.'/storage/app/core/installed.json');
        $this->artisan('core:status')->expectsOutputToContain('match the installed release')->assertExitCode(0);

        // Theme and site files may change freely.
        file_put_contents($this->site.'/resources/views/client/layouts/app.blade.php', 'new theme');
        file_put_contents($this->site.'/routes/theme.php', '<?php // more routes');
        file_put_contents($this->site.'/.env', 'APP_KEY=other');
        $this->artisan('core:status')->assertExitCode(0);

        file_put_contents($this->site.'/app/Models/Product.php', '<?php // hand edit');
        unlink($this->site.'/config/app.php');
        file_put_contents($this->site.'/app/Models/Hack.php', '<?php');

        $drift = (new CoreManifest($this->site))->drift();
        $this->assertSame(['app/Models/Product.php'], $drift['modified']);
        $this->assertSame(['config/app.php'], $drift['missing']);
        $this->assertSame(['app/Models/Hack.php'], $drift['added']);

        $this->artisan('core:status')->expectsOutputToContain('differ from the installed release')->assertExitCode(1);
    }

    public function test_backward_compatible_migrations_pass_and_destructive_ones_need_the_contract_marker(): void
    {
        $dir = $this->site.'/migrations';
        File::ensureDirectoryExists($dir);
        $write = function (string $name, string $up, string $extra = '') use ($dir): string {
            file_put_contents($dir.'/'.$name, "<?php\n{$extra}\nreturn new class {\n public function up(): void {\n{$up}\n }\n public function down(): void {\n Schema::dropIfExists('x'); \$t->dropColumn('a');\n }\n};");

            return $dir.'/'.$name;
        };

        $expand = $write('2026_10_07_000000_add.php', "Schema::table('products', function (\$t) { \$t->string('subtitle')->nullable(); });");
        $this->assertSame([], MigrationSafety::violations($expand), 'down() may drop what up() adds');

        $this->assertSame(['drops a column'], MigrationSafety::violations($write('2026_10_07_000001_a.php', "\$t->dropColumn('price');")));
        $this->assertSame(['renames a column'], MigrationSafety::violations($write('2026_10_07_000002_b.php', "\$t->renameColumn('a', 'b');")));
        $this->assertSame(['changes a column type'], MigrationSafety::violations($write('2026_10_07_000003_c.php', "\$t->integer('a')->change();")));
        $this->assertSame(['drops a table'], MigrationSafety::violations($write('2026_10_07_000004_d.php', "Schema::dropIfExists('old');")));
        $this->assertSame(['destructive SQL'], MigrationSafety::violations($write('2026_10_07_000005_e.php', "DB::statement('ALTER TABLE a DROP COLUMN b');")));

        $contract = $write('2026_10_07_000006_f.php', "\$t->dropColumn('legacy');", '// @contract-migration: legacy unused since 1.4.0');
        $this->assertSame([], MigrationSafety::violations($contract));
        $this->assertTrue(MigrationSafety::isContract($contract));

        $this->assertFalse(MigrationSafety::underPolicy($dir.'/2025_01_01_000000_old.php'));
        $this->assertTrue(MigrationSafety::underPolicy($expand));
    }

    /** CI gate: every migration written under the policy keeps the previous release working. */
    public function test_this_repositorys_migrations_are_backward_compatible(): void
    {
        $offenders = [];
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (MigrationSafety::underPolicy($file) && ($why = MigrationSafety::violations($file)) !== []) {
                $offenders[] = basename($file).': '.implode(', ', $why);
            }
        }

        $this->assertSame([], $offenders, "Expand/contract rule (AI_BUILD_PROMPT.md, \"Cập nhật phiên bản core\"): split into an expand release now and a contract release (marked @contract-migration) later.\n".implode("\n", $offenders));
    }

    public function test_a_theme_routes_file_is_loaded_under_the_locale_prefix(): void
    {
        $file = base_path('routes/theme.php');
        $this->assertFileDoesNotExist($file, 'routes/theme.php belongs to a theme, not to core');

        file_put_contents($file, "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::get('/theme-probe', fn () => 'theme ok')->name('theme-probe');\n");
        try {
            $this->refreshApplication();

            $this->assertTrue(Route::has('client.theme-probe'));
            $this->get('/vi/theme-probe')->assertOk()->assertSee('theme ok');
        } finally {
            unlink($file);
        }
    }
}
