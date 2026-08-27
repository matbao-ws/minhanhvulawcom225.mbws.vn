<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        (new \Database\Seeders\PermissionSeeder())->run();

        foreach (['menu', 'cms_page', 'catalog'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }

        $this->menu = Menu::query()->create(['key' => 'primary', 'name' => 'Menu chính', 'is_active' => true]);
    }

    private function actingWith(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Role '.md5(implode(',', $permissions)),
            'permissions' => $permissions,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);

        return $user;
    }

    private function page(string $slug = 'gioi-thieu'): Page
    {
        return Page::query()->create([
            'title' => ['vi' => 'Giới thiệu'],
            'slug' => $slug,
            'published_html' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    private function item(array $attributes = []): MenuItem
    {
        return $this->menu->items()->create(array_merge([
            'label' => ['vi' => 'Mục'],
            'type' => MenuItem::TYPE_URL,
            'url' => '/lien-he',
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    public function test_guests_cannot_reach_menu_management(): void
    {
        $this->get('/vi/admin/menus')->assertRedirect('/vi/admin/login');
    }

    public function test_menu_screens_require_the_menu_feature(): void
    {
        FeatureSetting::query()->where('feature_code', 'menu')->update(['is_enabled' => false]);
        $this->actingWith(['menus.view', 'menus.create', 'menus.update', 'menus.delete']);

        $this->get('/vi/admin/menus')->assertRedirect('/vi/admin');
    }

    public function test_view_only_role_cannot_create_or_delete_menus(): void
    {
        $this->actingWith(['menus.view']);

        $this->get('/vi/admin/menus')->assertOk();
        $this->get('/vi/admin/menus/create')->assertForbidden();
        $this->delete('/vi/admin/menus/'.$this->menu->id)->assertForbidden();

        $this->assertDatabaseHas('menus', ['id' => $this->menu->id]);
    }

    public function test_admin_can_create_a_menu(): void
    {
        $this->actingWith(['menus.view', 'menus.create']);

        $this->post('/vi/admin/menus', [
            'name' => 'Menu chân trang',
            'key' => 'Footer Menu',
            'is_active' => 1,
        ])->assertRedirect();

        // The key is slugged before validation so a typed label still works.
        $this->assertDatabaseHas('menus', ['key' => 'footer-menu', 'name' => 'Menu chân trang']);
    }

    public function test_duplicate_menu_key_is_rejected(): void
    {
        $this->actingWith(['menus.view', 'menus.create']);

        $this->post('/vi/admin/menus', ['name' => 'Khác', 'key' => 'primary', 'is_active' => 1])
            ->assertSessionHasErrors('key');

        $this->assertSame(1, Menu::query()->where('key', 'primary')->count());
    }

    public function test_admin_can_add_a_page_item_and_a_child_item(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);
        $page = $this->page();

        $this->post("/vi/admin/menus/{$this->menu->id}/items", [
            'label' => ['vi' => 'Giới thiệu'],
            'type' => MenuItem::TYPE_PAGE,
            'page_id' => $page->id,
            'is_active' => 1,
        ])->assertRedirect();

        $parent = MenuItem::query()->firstOrFail();
        $this->assertSame($page->id, $parent->page_id);

        $this->post("/vi/admin/menus/{$this->menu->id}/items", [
            'label' => ['vi' => 'Lịch sử'],
            'type' => MenuItem::TYPE_URL,
            'url' => '/lich-su',
            'parent_id' => $parent->id,
            'is_active' => 1,
        ])->assertRedirect();

        $child = MenuItem::query()->where('parent_id', $parent->id)->firstOrFail();
        $this->assertSame('/lich-su', $child->url);
        // Appended after its sibling rather than colliding on sort_order 0.
        $this->assertSame(1, $child->sort_order);
    }

    public function test_switching_type_clears_the_previous_target(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);
        $page = $this->page();
        $item = $this->item(['type' => MenuItem::TYPE_PAGE, 'page_id' => $page->id, 'url' => null]);

        $this->put("/vi/admin/menus/{$this->menu->id}/items/{$item->id}", [
            'label' => ['vi' => 'Mục'],
            'type' => MenuItem::TYPE_URL,
            'url' => 'https://example.test/tin-tuc',
            'is_active' => 1,
        ])->assertRedirect();

        $item->refresh();
        $this->assertNull($item->page_id);
        $this->assertSame('https://example.test/tin-tuc', $item->url);
    }

    public function test_unsafe_custom_urls_are_rejected(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);

        foreach (['javascript:alert(1)', 'data:text/html;base64,PHN2Zz4='] as $url) {
            $this->post("/vi/admin/menus/{$this->menu->id}/items", [
                'label' => ['vi' => 'Xấu'],
                'type' => MenuItem::TYPE_URL,
                'url' => $url,
                'is_active' => 1,
            ])->assertSessionHasErrors('url');
        }

        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_an_item_cannot_become_its_own_descendant(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);
        $parent = $this->item(['label' => ['vi' => 'Cha']]);
        $child = $this->item(['label' => ['vi' => 'Con'], 'parent_id' => $parent->id]);

        $this->put("/vi/admin/menus/{$this->menu->id}/items/{$parent->id}", [
            'label' => ['vi' => 'Cha'],
            'type' => MenuItem::TYPE_URL,
            'url' => '/lien-he',
            'parent_id' => $child->id,
            'is_active' => 1,
        ])->assertSessionHasErrors('parent_id');

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_items_cannot_be_managed_through_another_menu(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);
        $other = Menu::query()->create(['key' => 'footer', 'name' => 'Chân trang', 'is_active' => true]);
        $item = $this->item();

        $this->get("/vi/admin/menus/{$other->id}/items/{$item->id}/edit")->assertNotFound();
    }

    public function test_dragging_reorders_siblings(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);
        $first = $this->item(['label' => ['vi' => 'Một'], 'sort_order' => 0]);
        $second = $this->item(['label' => ['vi' => 'Hai'], 'sort_order' => 1]);

        $this->postJson("/vi/admin/menus/{$this->menu->id}/items/sort", [
            'ids' => [$second->id, $first->id],
            'start_order' => 0,
        ])->assertOk();

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_sorting_cannot_touch_items_of_another_menu(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);
        $other = Menu::query()->create(['key' => 'footer', 'name' => 'Chân trang', 'is_active' => true]);
        $foreign = $other->items()->create([
            'label' => ['vi' => 'Ngoài'],
            'type' => MenuItem::TYPE_URL,
            'url' => '/x',
            'is_active' => true,
            'sort_order' => 7,
        ]);

        $this->postJson("/vi/admin/menus/{$this->menu->id}/items/sort", [
            'ids' => [$foreign->id],
            'start_order' => 0,
        ])->assertOk();

        $this->assertSame(7, $foreign->fresh()->sort_order);
    }

    public function test_deleting_an_item_removes_its_children(): void
    {
        $this->actingWith(['menus.view', 'menus.update', 'menus.delete']);
        $parent = $this->item(['label' => ['vi' => 'Cha']]);
        $child = $this->item(['label' => ['vi' => 'Con'], 'parent_id' => $parent->id]);

        $this->delete("/vi/admin/menus/{$this->menu->id}/items/{$parent->id}")->assertRedirect();

        $this->assertDatabaseMissing('menu_items', ['id' => $parent->id]);
        $this->assertDatabaseMissing('menu_items', ['id' => $child->id]);
    }

    public function test_deleting_the_linked_page_leaves_the_item_without_a_target(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);
        $page = $this->page();
        $item = $this->item(['type' => MenuItem::TYPE_PAGE, 'page_id' => $page->id, 'url' => null]);

        $page->forceDelete();

        $item->refresh()->load('page');
        $this->assertNull($item->page_id);
        $this->assertTrue($item->hasMissingTarget());
    }

    public function test_category_items_resolve_to_the_storefront_category_page(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);
        $category = Category::query()->create([
            'name' => ['vi' => 'Điện thoại'],
            'slug' => 'dien-thoai',
            'is_active' => true,
        ]);

        $this->post("/vi/admin/menus/{$this->menu->id}/items", [
            'label' => ['vi' => 'Điện thoại'],
            'type' => MenuItem::TYPE_CATEGORY,
            'category_id' => $category->id,
            'is_active' => 1,
        ])->assertRedirect();

        $item = MenuItem::query()->firstOrFail();
        $this->assertSame($category->id, $item->category_id);
        $this->assertSame(
            route('client.categories.show', ['locale' => 'vi', 'slug' => 'dien-thoai']),
            app(\App\Services\MenuService::class)->resolveUrl($item, 'vi'),
        );
    }

    /**
     * A target that was deleted leaves the item with nothing to point at; it
     * must render as text rather than as a link into a 404.
     */
    public function test_an_item_whose_target_was_deleted_resolves_to_nothing(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);
        $item = $this->item(['type' => MenuItem::TYPE_CATEGORY, 'category_id' => null, 'url' => null]);

        $this->assertTrue($item->hasMissingTarget());
        $this->assertNull(app(\App\Services\MenuService::class)->resolveUrl($item, 'vi'));
    }

    public function test_sample_seeder_builds_a_nested_menu_and_is_idempotent(): void
    {
        // The seeder links items to the sample pages, so those come first.
        (new \Database\Seeders\PageContentSeeder())->run();
        (new \Database\Seeders\MenuSeeder())->run();

        $tree = app(\App\Services\MenuService::class)->tree('primary', 'vi');

        $this->assertSame('Trang chủ', $tree[0]['label']);
        $this->assertSame('/', $tree[0]['url']);
        $this->assertSame('Giới thiệu', $tree[1]['label']);
        $this->assertSame('Chính sách giao hàng', $tree[1]['children'][0]['label']);
        $this->assertNotEmpty(app(\App\Services\MenuService::class)->tree('footer', 'vi'));

        $countAfterFirstRun = MenuItem::query()->count();
        (new \Database\Seeders\MenuSeeder())->run();
        $this->assertSame($countAfterFirstRun, MenuItem::query()->count());
    }

    public function test_sample_seeder_skips_targets_this_installation_does_not_have(): void
    {
        (new \Database\Seeders\PageContentSeeder())->run();
        (new \Database\Seeders\MenuSeeder())->run();

        // No catalog category exists here, so the seeder must not leave an item
        // pointing at nothing.
        $this->assertSame(0, MenuItem::query()->where('type', MenuItem::TYPE_CATEGORY)->count());
        $this->assertSame(0, MenuItem::query()->where('type', MenuItem::TYPE_POST_CATEGORY)->count());
    }

    public function test_item_tree_screen_lists_nested_items(): void
    {
        $this->actingWith(['menus.view', 'menus.update']);
        $parent = $this->item(['label' => ['vi' => 'Cha']]);
        $this->item(['label' => ['vi' => 'Con'], 'parent_id' => $parent->id]);

        $this->get("/vi/admin/menus/{$this->menu->id}/items")
            ->assertOk()
            ->assertSee('Cha')
            ->assertSee('Con')
            ->assertSee('data-parent-id="'.$parent->id.'"', false);
    }
}
