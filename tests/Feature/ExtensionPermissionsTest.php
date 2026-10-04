<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Extensions\ExtensionManifest;
use App\Support\Extensions\ExtensionState;
use App\Support\Extensions\InvalidExtension;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsExtensions;
use Tests\TestCase;

/**
 * An extension's permissions (docs/EXTENSIONS_DESIGN.md, section 5): declared in extension.json,
 * shown and granted like core ones, enforced by the same Gate, never part of a wildcard, and kept
 * while the extension is switched off.
 */
class ExtensionPermissionsTest extends TestCase
{
    use BuildsExtensions;
    use RefreshDatabase;

    private string $id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeExtensionSite();
        $this->id = $this->uniqueExtensionId();
        $this->makeExtension($this->id, ['name' => 'Đại lý', 'permissions' => [
            ['code' => "{$this->id}.view", 'label' => 'Xem đại lý'],
            ['code' => "{$this->id}.sync", 'label' => 'Đồng bộ MISA'],
        ]], files: [
            'routes/admin.php' => "<?php use Illuminate\\Support\\Facades\\Route; Route::get('list', fn () => 'list')->middleware('can:{$this->id}.view')->name('list');",
        ]);
        $this->loadExtensions();
    }

    protected function tearDown(): void
    {
        $this->removeExtensionSite();
        parent::tearDown();
    }

    public function test_the_registry_lists_an_extensions_permissions_with_their_labels(): void
    {
        $this->assertContains("{$this->id}.view", PermissionRegistry::codes());
        $this->assertContains("{$this->id}.sync", PermissionRegistry::codes());
        $this->assertSame(
            ['group' => 'extensions', 'label' => 'Đại lý', 'actions' => ['view', 'sync'], 'action_labels' => ['view' => 'Xem đại lý', 'sync' => 'Đồng bộ MISA']],
            PermissionRegistry::modules()[$this->id],
        );
        $this->assertSame('Đồng bộ MISA — Đại lý', collect(PermissionRegistry::definitions())->firstWhere('code', "{$this->id}.sync")['name']);
        $this->assertNotContains("{$this->id}.view", PermissionRegistry::wildcardCodes(), 'granted on purpose, never by a wildcard');
    }

    public function test_a_role_grants_an_extension_permission_and_the_gate_enforces_it(): void
    {
        $superadmin = $this->userWith(['*'], system: true);

        $this->actingAs($superadmin)->get(route('admin.roles.create', ['locale' => 'vi']))->assertOk()
            ->assertSee('Tiện ích mở rộng')->assertSee('Đại lý')->assertSee('Đồng bộ MISA')
            ->assertSee('value="'.$this->id.'.sync"', false);

        // No permissions:sync ran: granting creates the missing permission row.
        $this->assertFalse(Permission::query()->where('code', "{$this->id}.view")->exists());
        $role = Role::query()->create(['name' => 'Đại lý viên', 'permissions' => ["{$this->id}.view"]]);
        $this->assertSame(["{$this->id}.view"], $role->fresh()->permissions);

        $dealer = User::factory()->create(['role_id' => $role->id]);
        $other = $this->userWith(['orders.view']);
        $this->actingAs($dealer)->get("/vi/admin/ext/{$this->id}/list")->assertOk();
        $this->actingAs($other)->get("/vi/admin/ext/{$this->id}/list")->assertForbidden();
    }

    public function test_prune_keeps_the_permissions_of_an_extension_that_is_only_switched_off(): void
    {
        Role::query()->create(['name' => 'Đại lý viên', 'permissions' => ["{$this->id}.view"]]);
        Permission::query()->create(['code' => 'gone.view', 'name' => 'x', 'group' => 'extensions', 'description' => 'x']);
        (new ExtensionState($this->extensionSite))->disable($this->id);
        $this->loadExtensions();

        $this->artisan('permissions:sync', ['--prune' => true])->assertExitCode(0);

        $this->assertTrue(Permission::query()->where('code', "{$this->id}.view")->exists());
        $this->assertFalse(Permission::query()->where('code', 'gone.view')->exists(), 'no folder left: pruned');
    }

    public function test_an_extension_may_not_take_a_core_permission_module(): void
    {
        $this->makeExtension('orders');

        $this->expectException(InvalidExtension::class);
        $this->expectExceptionMessage('id "orders" is a core permission module');
        ExtensionManifest::fromDirectory($this->extensionSite.'/extensions/orders');
    }

    public function test_actions_outside_the_five_columns_are_grantable_in_the_role_form(): void
    {
        // ai.apply had no column before; it now sits in "Khác" like an extension's own actions.
        $this->actingAs($this->userWith(['*'], system: true))->get(route('admin.roles.create', ['locale' => 'vi']))
            ->assertOk()->assertSee('value="ai.apply"', false);
    }

    /** @param  array<int, string>  $permissions */
    private function userWith(array $permissions, bool $system = false): User
    {
        $role = Role::query()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions, 'is_system' => $system]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
