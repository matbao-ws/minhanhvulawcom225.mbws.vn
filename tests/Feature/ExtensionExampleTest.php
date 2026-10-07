<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\CoreManifest;
use App\Support\Extensions\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsExtensions;
use Tests\TestCase;

/**
 * The reference extension in tests/Fixtures/extensions/example, end to end on a website: its own
 * table, admin page, menu entry and permissions — and it passes extension:check. It lives under
 * tests/ so the core repository itself never loads it.
 */
class ExtensionExampleTest extends TestCase
{
    use BuildsExtensions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeExtensionSite();
        File::copyDirectory(base_path('tests/Fixtures/extensions/example'), $this->extensionSite.'/extensions/example');
    }

    protected function tearDown(): void
    {
        $this->removeExtensionSite();
        parent::tearDown();
    }

    public function test_the_example_extension_works_end_to_end(): void
    {
        $manager = $this->loadExtensions();
        $this->assertSame(ExtensionManager::LOADED, $manager->all()['example']['status'], (string) $manager->all()['example']['error']);

        // Its migration runs with the website's: php artisan migrate.
        $this->artisan('migrate')->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('ext_example_notes'));

        $writer = $this->user(['example.view', 'example.manage'], 'Chủ shop');
        $reader = $this->user(['example.view']);
        $stranger = $this->user(['orders.view']);

        $this->actingAs($writer)->get('/vi/admin')->assertOk()->assertSee('Ghi chú nội bộ');
        $this->actingAs($writer)->post('/vi/admin/ext/example/notes', ['body' => 'Gọi lại khách lúc 3h'])
            ->assertRedirect('/vi/admin/ext/example/notes');
        $this->actingAs($reader)->get('/vi/admin/ext/example/notes')->assertOk()
            ->assertSee('Gọi lại khách lúc 3h')->assertSee('Chủ shop')->assertDontSee('Lưu ghi chú');

        $this->actingAs($reader)->post('/vi/admin/ext/example/notes', ['body' => 'x'])->assertForbidden();
        $this->actingAs($stranger)->get('/vi/admin/ext/example/notes')->assertForbidden();
        $this->actingAs($stranger)->get('/vi/admin')->assertOk()->assertDontSee('Ghi chú nội bộ');
        $this->actingAs($writer)->post('/vi/admin/ext/example/notes', ['body' => ''])->assertSessionHasErrors('body');
    }

    public function test_the_example_extension_passes_its_checks(): void
    {
        $this->app->instance(ExtensionManager::class, new ExtensionManager($this->extensionSite, new \App\Support\Extensions\ExtensionState($this->extensionSite)));
        $this->app->instance(CoreManifest::class, new CoreManifest($this->extensionSite));

        $this->artisan('extension:check', ['id' => 'example'])->expectsOutputToContain('example 1.0.0: OK')->assertExitCode(0);
    }

    /** @param  array<int, string>  $permissions */
    private function user(array $permissions, string $name = 'Nhân viên'): User
    {
        $role = Role::query()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions]);

        return User::factory()->create(['role_id' => $role->id, 'name' => $name]);
    }
}
