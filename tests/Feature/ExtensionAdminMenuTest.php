<?php

namespace Tests\Feature;

use App\Extension\Registries\AdminMenu;
use App\Models\Role;
use App\Models\User;
use App\Support\Extensions\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Tests\Support\BuildsExtensions;
use Tests\TestCase;

/**
 * An extension's admin sidebar entries (docs/EXTENSIONS_DESIGN.md, section 5): registered through
 * AdminMenu, drawn by the core sidebar for accounts allowed to open them, and a clash fails the
 * extension instead of overriding anything.
 */
class ExtensionAdminMenuTest extends TestCase
{
    use BuildsExtensions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeExtensionSite();
        URL::defaults(['locale' => 'vi']); // what the admin middleware sets on a real request
    }

    protected function tearDown(): void
    {
        $this->removeExtensionSite();
        parent::tearDown();
    }

    public function test_the_sidebar_shows_an_extensions_menu_to_accounts_allowed_to_open_it(): void
    {
        $id = $this->uniqueExtensionId();
        $this->makeExtension($id, ['permissions' => [
            ['code' => "{$id}.view", 'label' => 'Xem'],
            ['code' => "{$id}.manage", 'label' => 'Quản lý'],
        ]], files: [
            'lang/vi/menu.php' => "<?php return ['dealers' => 'Đại lý', 'commissions' => 'Hoa hồng'];",
            'routes/admin.php' => "<?php use Illuminate\\Support\\Facades\\Route;
                Route::get('dealers', fn () => 'dealers')->middleware('can:{$id}.view')->name('index');
                Route::get('commissions', fn () => 'commissions')->middleware('can:{$id}.manage')->name('commissions');",
        ], boot: "\\App\\Extension\\Registries\\AdminMenu::register('{$id}', [
            'key' => '{$id}.index', 'label' => __('{$id}::menu.dealers'), 'icon' => 'solar:handshake-line-duotone', 'permission' => '{$id}.view',
            'children' => [
                ['key' => '{$id}.list', 'label' => __('{$id}::menu.dealers'), 'route' => 'ext.{$id}.index', 'permission' => '{$id}.view'],
                ['key' => '{$id}.commissions', 'label' => __('{$id}::menu.commissions'), 'route' => 'ext.{$id}.commissions', 'permission' => '{$id}.manage'],
            ],
        ]);");
        $this->loadExtensions();

        $viewer = $this->userWith(["{$id}.view"]);
        $page = $this->actingAs($viewer)->get('/vi/admin')->assertOk();
        $page->assertSee('data-extension-menu="'.$id.'.index"', false)
            ->assertSee('Đại lý')->assertSee("/vi/admin/ext/{$id}/dealers", false)
            ->assertDontSee("/vi/admin/ext/{$id}/commissions", false);

        $this->actingAs($this->userWith(['orders.view']))->get('/vi/admin')->assertOk()
            ->assertDontSee('data-extension-menu="'.$id.'.index"', false);

        $this->actingAs($this->userWith(["{$id}.view", "{$id}.manage"]))->get('/vi/admin')->assertOk()
            ->assertSee('Hoa hồng')->assertSee("/vi/admin/ext/{$id}/commissions", false);
    }

    public function test_a_clashing_key_fails_the_extension_and_removes_its_entries(): void
    {
        $first = $this->uniqueExtensionId('a');
        $second = $this->uniqueExtensionId('b');
        $route = "<?php use Illuminate\\Support\\Facades\\Route; Route::get('home', fn () => 'home')->name('home');";
        $this->makeExtension($first, files: ['routes/admin.php' => $route],
            boot: "\\App\\Extension\\Registries\\AdminMenu::register('{$first}', ['key' => '{$first}.home', 'label' => 'First', 'route' => 'ext.{$first}.home']);");
        // Registers one entry, then clashes with its own key: the first one must go too.
        $this->makeExtension($second, files: ['routes/admin.php' => $route], boot: "
            \\App\\Extension\\Registries\\AdminMenu::register('{$second}', ['key' => '{$second}.home', 'label' => 'Second', 'route' => 'ext.{$second}.home']);
            \\App\\Extension\\Registries\\AdminMenu::register('{$second}', ['key' => '{$second}.home', 'label' => 'Again', 'route' => 'ext.{$second}.home']);");

        $all = $this->loadExtensions()->all();

        $this->assertSame(ExtensionManager::LOADED, $all[$first]['status']);
        $this->assertSame(ExtensionManager::FAILED, $all[$second]['status']);
        $this->assertStringContainsString('already registered', $all[$second]['error']);
        $keys = array_column(app(AdminMenu::class)->visibleTo($this->userWith(['*'], true)), 'key');
        $this->assertSame(["{$first}.home"], $keys);
    }

    public function test_malformed_entries_are_refused(): void
    {
        $menu = new AdminMenu;
        $cases = [
            'key of another extension' => [['key' => 'misa.home', 'label' => 'x', 'route' => 'r'], 'must start with "dealers."'],
            'no label' => [['key' => 'dealers.home', 'route' => 'r'], 'needs a label'],
            'neither route nor children' => [['key' => 'dealers.home', 'label' => 'x'], 'needs a route or children'],
            'foreign permission' => [['key' => 'dealers.home', 'label' => 'x', 'route' => 'r', 'permission' => 'misa.view'], 'unknown permission "misa.view"'],
        ];
        foreach ($cases as $name => [$item, $error]) {
            try {
                $menu->add('dealers', $item);
                $this->fail("{$name}: accepted");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($error, $e->getMessage(), $name);
            }
        }

        // A core permission is fine.
        $menu->add('dealers', ['key' => 'dealers.orders', 'label' => 'Đơn đại lý', 'route' => 'admin.orders.index', 'permission' => 'orders.view']);
        $this->assertCount(1, $menu->visibleTo($this->userWith(['orders.view'])));
    }

    /** @param  array<int, string>  $permissions */
    private function userWith(array $permissions, bool $system = false): User
    {
        $role = Role::query()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions, 'is_system' => $system]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
