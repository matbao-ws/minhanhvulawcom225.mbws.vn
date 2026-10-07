<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Extensions\ExtensionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsExtensions;
use Tests\TestCase;

/** The superadmin's Extensions page: what became of each extension, read-only. */
class ExtensionAdminPageTest extends TestCase
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

    public function test_the_superadmin_sees_each_extension_and_why_one_is_not_running(): void
    {
        $running = $this->uniqueExtensionId('r');
        $broken = $this->uniqueExtensionId('b');
        $off = $this->uniqueExtensionId('o');
        $this->makeExtension($running, ['name' => 'Quản lý đại lý', 'version' => '1.4.0']);
        $this->makeExtension($broken, boot: "throw new \\RuntimeException('MISA API key missing');");
        $this->makeExtension($off);
        (new ExtensionState($this->extensionSite))->disable($off, 'chờ bản mới');
        $this->loadExtensions();

        $page = $this->actingAs($this->user(['*'], true))->get(route('admin.extensions.index', ['locale' => 'vi']))->assertOk();

        $page->assertSee('Quản lý đại lý')->assertSee('1.4.0')->assertSee('Đang chạy')
            // The error itself never reaches the page: a sentence and the reference it was logged under.
            ->assertSee('Lỗi khi nạp')->assertDontSee('MISA API key missing')
            ->assertSee('Tiện ích gặp lỗi khi khởi động')->assertSee('Mã tra cứu: EXT-')
            ->assertSee('Đã tắt')->assertSee('chờ bản mới')
            ->assertSee('php artisan extension:disable');
    }

    public function test_only_the_superadmin_opens_it(): void
    {
        $this->actingAs($this->user(['settings.view', 'settings.update']))
            ->get(route('admin.extensions.index', ['locale' => 'vi']))->assertForbidden();
    }

    public function test_no_extension_shows_an_empty_page(): void
    {
        $this->loadExtensions();

        $this->actingAs($this->user(['*'], true))->get(route('admin.extensions.index', ['locale' => 'vi']))
            ->assertOk()->assertSee('Website chưa có tiện ích mở rộng nào.');
    }

    /** @param  array<int, string>  $permissions */
    private function user(array $permissions, bool $system = false): User
    {
        $role = Role::query()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions, 'is_system' => $system]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
