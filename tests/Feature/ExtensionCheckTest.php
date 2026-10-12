<?php

namespace Tests\Feature;

use App\Support\CoreManifest;
use App\Support\Extensions\ExtensionChecker;
use App\Support\Extensions\ExtensionManager;
use App\Support\Extensions\ExtensionState;
use App\Support\MigrationSafety;
use Illuminate\Support\Facades\File;
use Tests\Support\BuildsExtensions;
use Tests\TestCase;

/**
 * extension:check (docs/EXTENSIONS_DESIGN.md, sections 3 and 5): extension API only, no replaced
 * core class, migrations on their own tables and expand/contract.
 */
class ExtensionCheckTest extends TestCase
{
    use BuildsExtensions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeExtensionSite();
        $this->app->instance(ExtensionManager::class, new ExtensionManager($this->extensionSite, new ExtensionState($this->extensionSite)));
        $this->app->instance(CoreManifest::class, new CoreManifest($this->extensionSite));
    }

    protected function tearDown(): void
    {
        $this->removeExtensionSite();
        parent::tearDown();
    }

    public function test_a_clean_extension_passes(): void
    {
        $this->artisan('extension:make', ['id' => 'dealers'])->assertExitCode(0);
        $this->writeFile('dealers', 'database/migrations/2026_11_01_000000_create_dealers_table.php', <<<'PHP'
            <?php
            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            return new class extends Migration {
                public function up(): void
                {
                    Schema::create('ext_dealers_dealers', function (Blueprint $table) {
                        $table->id();
                        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                        $table->string('name');
                    });
                }
                public function down(): void { Schema::dropIfExists('ext_dealers_dealers'); }
            };
            PHP);
        $this->writeFile('dealers', 'src/Reports.php', <<<'PHP'
            <?php
            namespace Extensions\Dealers;
            use App\Extension\Registries\AdminMenu;
            use App\Models\Order;
            class Reports { public function total() { return Order::query()->count(); } }
            PHP);

        $this->artisan('extension:check', ['id' => 'dealers'])->expectsOutputToContain('dealers 0.1.0: OK')->assertExitCode(0);
    }

    public function test_private_core_classes_and_replaced_services_are_errors(): void
    {
        $checker = new ExtensionChecker;

        $problems = $checker->scanPhp(<<<'PHP'
            <?php
            namespace Extensions\Misa;
            use App\Services\Orders\OrderCreationService;
            use App\Support\FeatureGate as Gate;
            use App\Models\Voucher;
            class P {
                public function register() {
                    $this->app->bind(Gate::class, MyGate::class);
                    app()->singleton('App\Services\ShippingService', fn () => null);
                    \App\Support\MediaUrl::make('x');
                    $this->app->singleton(MyOwn::class);
                    App::extend(\App\Services\MailSettings::class, fn ($s) => $s);
                }
            }
            PHP);

        $this->assertContains('uses App\Services\Orders\OrderCreationService, which is not part of the extension API', $problems);
        $this->assertContains('uses App\Support\FeatureGate, which is not part of the extension API', $problems);
        $this->assertContains('uses App\Models\Voucher, which is not part of the extension API', $problems);
        $this->assertContains('bind(App\Support\FeatureGate) replaces a core class: extensions add, listen and register, never replace', $problems);
        $this->assertContains('singleton(App\Services\ShippingService) replaces a core class: extensions add, listen and register, never replace', $problems);
        $this->assertContains('uses App\Support\MediaUrl, which is not part of the extension API', $problems);
        $this->assertContains('extend(App\Services\MailSettings) replaces a core class: extensions add, listen and register, never replace', $problems);
        $this->assertEmpty(array_filter($problems, fn ($p) => str_contains($p, 'MyOwn')), 'binding its own class is fine');

        $this->assertSame(['uses App\Services\Cart, which is not part of the extension API'],
            $checker->scanTemplate('<div>{{ \App\Services\Cart::count() }} {{ \App\Extension\Registries\AdminMenu::class }}</div>'));
    }

    public function test_migrations_may_only_touch_their_own_tables(): void
    {
        $file = $this->extensionSite.'/m.php';
        file_put_contents($file, <<<'PHP'
            <?php
            Schema::create('ext_misa_logs', fn ($t) => $t->id());
            Schema::table('orders', fn ($t) => $t->string('misa_id')->nullable());
            Schema::table('ext_dealers_dealers', fn ($t) => $t->string('x'));
            DB::table('permissions')->insert(['code' => 'misa.view']);
            Schema::create($name, fn ($t) => $t->id());
            Schema::rename('ext_misa_old', 'products');
            PHP);

        $this->assertSame([
            "Schema::table('orders'): not its own table (must start with ext_misa_)",
            "Schema::table('ext_dealers_dealers'): not its own table (must start with ext_misa_)",
            "DB::table('permissions'): not its own table (must start with ext_misa_)",
            'Schema::create($name): the table name must be a literal string',
            "Schema::rename('products'): not its own table (must start with ext_misa_)",
        ], MigrationSafety::foreignTables($file, 'ext_misa_'));
    }

    public function test_the_command_fails_on_errors_and_lists_cascade_warnings(): void
    {
        $this->makeExtension('misa', files: [
            'src/Bad.php' => '<?php namespace Extensions\\Misa; use App\\Services\\ShippingService; class Bad {}',
            'database/migrations/2026_11_01_000000_x.php' => "<?php return new class extends Illuminate\\Database\\Migrations\\Migration { public function up(): void {
                Schema::create('ext_misa_logs', function (\$t) { \$t->foreignId('order_id')->constrained('orders')->cascadeOnDelete(); });
                Schema::table('ext_misa_logs', function (\$t) { \$t->dropColumn('x'); });
            } };",
        ]);

        $this->artisan('extension:check')
            ->expectsOutputToContain('uses App\Services\ShippingService')
            ->expectsOutputToContain('drops a column')
            ->expectsOutputToContain('deletes in cascade')
            ->assertExitCode(1);
    }

    public function test_an_invalid_or_incompatible_extension_fails_the_check(): void
    {
        $this->makeExtension('old', ['requires_core' => '<1.0.0']);
        $this->makeExtension('broken', ['id' => 'other']);

        $this->artisan('extension:check')
            ->expectsOutputToContain('requires core <1.0.0, this core is 1.2.0')
            ->expectsOutputToContain('must equal the folder name')
            ->assertExitCode(1);
    }

    /** Every rule the project CI relies on fails the check on its own — exit 1, not a warning. */
    public function test_each_rule_fails_the_check_with_a_non_zero_exit(): void
    {
        $migration = fn (string $body) => "<?php return new class extends Illuminate\\Database\\Migrations\\Migration { public function up(): void { {$body} } };";
        $cases = [
            'App\\Services in use' => [[], ['src/A.php' => '<?php namespace Extensions\\Misa; use App\\Services\\MailSettings; class A {}'], 'not part of the extension API'],
            'core binding replaced' => [[], ['src/A.php' => '<?php namespace Extensions\\Misa; class A { function r() { app()->singleton(\\App\\Support\\FeatureGate::class, fn () => null); } }'], 'replaces a core class'],
            'migration on a core table' => [[], ['database/migrations/2026_11_01_000000_a.php' => $migration("Schema::table('orders', fn (\$t) => \$t->string('x')->nullable());")], 'not its own table'],
            'table with the wrong prefix' => [[], ['database/migrations/2026_11_01_000000_a.php' => $migration("Schema::create('misa_logs', fn (\$t) => \$t->id());")], 'must start with ext_misa_'],
            'permission with the wrong prefix' => [['permissions' => [['code' => 'orders.view', 'label' => 'x']]], [], 'must be "misa.<action>"'],
            'route outside routes/' => [[], ['src/A.php' => '<?php namespace Extensions\\Misa; use Illuminate\\Support\\Facades\\Route; class A { function b() { Route::get("/admin/x", fn () => 1); } }'], 'escape /ext/<id>'],
            'loading its own routes' => [[], ['src/A.php' => '<?php namespace Extensions\\Misa; class A extends \\Illuminate\\Support\\ServiceProvider { function boot() { $this->loadRoutesFrom(__DIR__."/r.php"); } }'], 'the core loads'],
            'invalid manifest' => [['schema' => 9], [], 'schema must be 1'],
        ];

        foreach ($cases as $name => [$manifest, $files, $expected]) {
            File::deleteDirectory($this->extensionSite.'/extensions');
            $this->makeExtension('misa', $manifest, $files);
            app(ExtensionManager::class)->forget();

            $this->artisan('extension:check')->expectsOutputToContain($expected)->assertExitCode(1);
        }
    }

    private function writeFile(string $id, string $path, string $contents): void
    {
        $file = $this->extensionSite.'/extensions/'.$id.'/'.$path;
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, $contents);
    }
}
