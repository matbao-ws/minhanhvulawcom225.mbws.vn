<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Extensions\ExtensionManager;
use App\Support\Extensions\ExtensionManifest;
use App\Support\Extensions\ExtensionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsExtensions;
use Tests\TestCase;

/** extension:list, :enable, :disable and :make (docs/EXTENSIONS_DESIGN.md, section 4). */
class ExtensionCommandsTest extends TestCase
{
    use BuildsExtensions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeExtensionSite();
        $this->app->instance(ExtensionManager::class, new ExtensionManager($this->extensionSite, new ExtensionState($this->extensionSite)));
        $this->app->instance(\App\Support\CoreManifest::class, new \App\Support\CoreManifest($this->extensionSite));
    }

    protected function tearDown(): void
    {
        $this->removeExtensionSite();
        parent::tearDown();
    }

    public function test_list_shows_every_extension_with_its_status(): void
    {
        $this->makeExtension('dealers', ['name' => 'Đại lý']);
        $this->makeExtension('misa', ['requires_core' => '>=3.0.0']);
        $this->makeExtension('broken', ['id' => 'other']);

        $this->artisan('extension:list')
            ->expectsOutputToContain('Đại lý')
            ->expectsOutputToContain('incompatible')
            ->expectsOutputToContain('invalid')
            ->assertExitCode(0);

        $this->artisan('extension:list', ['--json' => true])->expectsOutputToContain('"status": "ready"')->assertExitCode(0);
    }

    public function test_disable_and_enable_switch_an_extension_through_the_state_file(): void
    {
        $this->makeExtension('dealers');

        $this->artisan('extension:disable', ['id' => 'dealers', '--reason' => 'lỗi MISA'])->assertExitCode(0);
        $this->assertSame('lỗi MISA', (new ExtensionState($this->extensionSite))->disabled()['dealers']['reason']);

        $this->artisan('extension:enable', ['id' => 'dealers'])->expectsOutputToContain('is on')->assertExitCode(0);
        $this->assertFalse((new ExtensionState($this->extensionSite))->isDisabled('dealers'));

        $this->artisan('extension:disable', ['id' => 'nothere'])->assertExitCode(1);
    }

    public function test_enable_says_when_an_extension_still_cannot_load(): void
    {
        $this->makeExtension('misa', ['requires_core' => '>=3.0.0']);
        (new ExtensionState($this->extensionSite))->disable('misa');

        $this->artisan('extension:enable', ['id' => 'misa'])->expectsOutputToContain('will not load: incompatible')->assertExitCode(1);
    }

    public function test_extension_commands_never_run_extension_code(): void
    {
        $id = $this->uniqueExtensionId();
        $this->makeExtension($id, register: "throw new \RuntimeException('must not run');");
        $argv = $_SERVER['argv'];
        $_SERVER['argv'] = ['artisan', 'extension:disable', $id];

        try {
            $manager = $this->loadExtensions();
        } finally {
            $_SERVER['argv'] = $argv;
        }

        $this->assertSame(ExtensionManager::READY, $manager->all()[$id]['status'], 'not loaded, so not failed either');
    }

    public function test_make_creates_a_skeleton_that_loads_and_serves_its_admin_page(): void
    {
        $id = $this->uniqueExtensionId('m');

        $this->artisan('extension:make', ['id' => $id, '--name' => 'Thử nghiệm'])->assertExitCode(0);

        $manifest = ExtensionManifest::fromDirectory($this->extensionSite.'/extensions/'.$id);
        $this->assertSame('>=1.2.0 <2.0.0', $manifest->requiresCore);
        $this->assertSame(["{$id}.view"], $manifest->permissionCodes());

        $manager = $this->loadExtensions();
        $this->assertSame(ExtensionManager::LOADED, $manager->all()[$id]['status'], (string) $manager->all()[$id]['error']);

        $role = Role::query()->create(['name' => 'Superadmin', 'permissions' => ['*'], 'is_system' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]))
            ->get("/vi/admin/ext/{$id}")->assertOk()->assertSee('Trang của extension Thử nghiệm');

        $this->artisan('extension:make', ['id' => $id])->expectsOutputToContain('already exists')->assertExitCode(1);
        $this->artisan('extension:make', ['id' => 'orders'])->expectsOutputToContain('core permission module')->assertExitCode(1);
        $this->artisan('extension:make', ['id' => 'Bad-Id'])->assertExitCode(1);
    }
}
