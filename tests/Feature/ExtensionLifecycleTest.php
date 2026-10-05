<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Extensions\ExtensionAutoloader;
use App\Support\Extensions\ExtensionManager;
use App\Support\Extensions\ExtensionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\Support\BuildsExtensions;
use Tests\TestCase;

/**
 * Loading an extension (docs/EXTENSIONS_DESIGN.md, section 2): the core owns the lifecycle and the
 * route prefixes; a broken extension is skipped, never the website.
 */
class ExtensionLifecycleTest extends TestCase
{
    use BuildsExtensions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeExtensionSite();
    }

    protected function tearDown(): void
    {
        $this->removeExtensionSite();
        parent::tearDown();
    }

    public function test_an_extension_gets_its_admin_page_views_translations_and_config(): void
    {
        $id = $this->uniqueExtensionId();
        $this->makeExtension($id, files: [
            'config.php' => "<?php return ['greeting' => 'Xin chào'];",
            'lang/vi/messages.php' => "<?php return ['title' => 'Trang đại lý'];",
            'views/hello.blade.php' => "<h1>{{ __('{$id}::messages.title') }}</h1><p>{{ config('extensions.{$id}.greeting') }}</p>",
            'routes/admin.php' => "<?php use Illuminate\\Support\\Facades\\Route; Route::get('hello', fn () => view('{$id}::hello'))->name('hello');",
            'routes/api.php' => "<?php use Illuminate\\Support\\Facades\\Route; Route::get('ping', fn () => ['ok' => true])->name('ping');",
            'routes/web.php' => "<?php use Illuminate\\Support\\Facades\\Route; Route::get('callback', fn () => 'callback')->name('callback');",
        ]);

        $manager = $this->loadExtensions();

        $this->assertSame(ExtensionManager::LOADED, $manager->all()[$id]['status']);
        $this->assertSame("/vi/admin/ext/{$id}/hello", route("ext.{$id}.hello", ['locale' => 'vi'], false));

        // The admin group's guards: a guest goes to the login page.
        $this->get("/vi/admin/ext/{$id}/hello")->assertRedirect(route('admin.login', ['locale' => 'vi']));

        $this->actingAs($this->superadmin())->get("/vi/admin/ext/{$id}/hello")
            ->assertOk()->assertSee('Trang đại lý')->assertSee('Xin chào');
        $this->getJson("/api/ext/{$id}/ping")->assertOk()->assertJson(['ok' => true]);
        $this->get("/ext/{$id}/callback")->assertOk()->assertSee('callback');
    }

    public function test_admin_controllers_get_their_own_parameters_without_the_locale(): void
    {
        $id = $this->uniqueExtensionId();
        $studly = ucfirst($id);
        $this->makeExtension($id, files: [
            'src/ItemController.php' => "<?php namespace Extensions\\{$studly}; class ItemController extends \\Illuminate\\Routing\\Controller {
                public function show(int \$item) { return 'item:'.\$item.' locale:'.app()->getLocale(); } }",
            'routes/admin.php' => "<?php use Illuminate\\Support\\Facades\\Route; Route::get('items/{item}', [Extensions\\{$studly}\\ItemController::class, 'show'])->name('item');",
        ]);
        $this->loadExtensions();

        $this->actingAs($this->superadmin())->get("/en/admin/ext/{$id}/items/7")->assertOk()->assertSee('item:7 locale:en');
        $this->assertSame("/vi/admin/ext/{$id}/items/7", route("ext.{$id}.item", ['locale' => 'vi', 'item' => 7], false));
    }

    public function test_a_provider_that_throws_in_register_or_boot_fails_alone(): void
    {
        $inRegister = $this->uniqueExtensionId('r');
        $inBoot = $this->uniqueExtensionId('b');
        $healthy = $this->uniqueExtensionId('h');
        $this->makeExtension($inRegister, register: "throw new \\RuntimeException('register broke');");
        $this->makeExtension($inBoot, boot: "throw new \\RuntimeException('boot broke');", files: [
            'routes/admin.php' => "<?php use Illuminate\\Support\\Facades\\Route; Route::get('x', fn () => 'x')->name('x');",
        ]);
        $this->makeExtension($healthy);

        $all = $this->loadExtensions()->all();

        $this->assertSame(ExtensionManager::FAILED, $all[$inRegister]['status']);
        $this->assertStringContainsString('register broke', $all[$inRegister]['error']);
        $this->assertSame(ExtensionManager::FAILED, $all[$inBoot]['status']);
        $this->assertFalse(Route::has("ext.{$inBoot}.x"), 'a failed extension does not get its routes');
        $this->assertSame(ExtensionManager::LOADED, $all[$healthy]['status']);

        // Pages that have nothing to do with the broken extensions keep working.
        $this->actingAs($this->superadmin())->get('/vi/admin')->assertOk();
        $this->artisan('core:health')->assertExitCode(0);
    }

    public function test_a_failure_is_logged_with_id_version_phase_and_class_but_no_secret(): void
    {
        $inBoot = $this->uniqueExtensionId('b');
        $inRegister = $this->uniqueExtensionId('r');
        $this->makeExtension($inBoot, ['version' => '2.3.4'], files: [
            'config.php' => "<?php return ['api_key' => 'SECRET-MISA-KEY-123'];",
        ], boot: "(function (string \$key) { throw new \\RuntimeException('MISA unreachable'); })(config('extensions.{$inBoot}.api_key'));");
        $this->makeExtension($inRegister, register: "throw new \\LogicException('bad wiring');");
        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = ['message' => $event->message, 'context' => $event->context];
        });

        $all = $this->loadExtensions()->all();

        $boot = collect($logged)->firstWhere('context.extension', $inBoot);
        $this->assertSame("Extension {$inBoot} failed to load (boot)", $boot['message']);
        $this->assertSame(['extension' => $inBoot, 'version' => '2.3.4', 'phase' => 'boot', 'exception' => 'RuntimeException', 'message' => 'MISA unreachable'],
            array_intersect_key($boot['context'], array_flip(['extension', 'version', 'phase', 'exception', 'message'])));
        $this->assertStringStartsWith("extensions/{$inBoot}/src/", $boot['context']['at']);
        $this->assertSame('register', collect($logged)->firstWhere('context.extension', $inRegister)['context']['phase']);
        $this->assertSame('boot: RuntimeException: MISA unreachable', $all[$inBoot]['error']);

        $everything = json_encode($logged).json_encode($all);
        $this->assertStringNotContainsString('SECRET-MISA-KEY-123', $everything, 'no trace arguments, no config in the log or the admin');
    }

    public function test_extensions_enabled_false_boots_past_a_badly_broken_extension(): void
    {
        $id = $this->uniqueExtensionId();
        $this->makeExtension($id, files: ["src/Broken.php" => '<?php this is not php']);
        file_put_contents($this->extensionSite.'/extensions/'.$id.'/src/'.ucfirst($id).'ServiceProvider.php', '<?php class {');
        $this->makeExtension('zzbroken');
        file_put_contents($this->extensionSite.'/extensions/zzbroken/extension.json', '{ not json');
        config(['extensions.enabled' => false]);

        $manager = $this->loadExtensions();

        $this->assertSame([], $manager->loaded());
        $this->actingAs($this->superadmin())->get('/vi/admin')->assertOk();
    }

    public function test_a_parse_error_in_an_extension_only_fails_that_extension(): void
    {
        $id = $this->uniqueExtensionId();
        $this->makeExtension($id);
        file_put_contents($this->extensionSite.'/extensions/'.$id.'/src/'.ucfirst($id).'ServiceProvider.php', '<?php class {');

        $all = $this->loadExtensions()->all();

        $this->assertSame(ExtensionManager::FAILED, $all[$id]['status']);
        $this->actingAs($this->superadmin())->get('/vi/admin')->assertOk();
    }

    public function test_disabled_and_incompatible_extensions_are_not_loaded(): void
    {
        $off = $this->uniqueExtensionId('o');
        $old = $this->uniqueExtensionId('i');
        $this->makeExtension($off, register: "throw new \\RuntimeException('must not run');");
        $this->makeExtension($old, ['requires_core' => '<1.0.0'], register: "throw new \\RuntimeException('must not run');");
        (new ExtensionState($this->extensionSite))->disable($off);

        $all = $this->loadExtensions()->all();

        $this->assertSame(ExtensionManager::DISABLED, $all[$off]['status']);
        $this->assertSame(ExtensionManager::INCOMPATIBLE, $all[$old]['status']);
    }

    public function test_the_autoloader_only_maps_an_extensions_own_namespace(): void
    {
        $id = $this->uniqueExtensionId();
        $studly = ucfirst($id);
        $this->makeExtension($id, files: [
            'src/Support/Greeter.php' => "<?php namespace Extensions\\{$studly}\\Support; class Greeter { public static function hi() { return 'hi'; } }",
        ]);
        $this->loadExtensions();

        $this->assertSame('hi', ("Extensions\\{$studly}\\Support\\Greeter")::hi());

        // Path tricks never become a file include.
        file_put_contents($this->extensionSite.'/evil.php', '<?php throw new \RuntimeException("included");');
        ExtensionAutoloader::load("Extensions\\{$studly}\\..\\..\\evil");
        $this->assertFalse(class_exists("Extensions\\{$studly}\\Missing"));
    }

    private function superadmin(): User
    {
        $role = Role::query()->create(['name' => 'Superadmin', 'permissions' => ['*'], 'is_system' => true]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
