<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront side: content pages are reached through the menu, and the
 * Blade layout and the public API read the same service.
 */
class StorefrontMenuTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['menu', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }

        $this->menu = Menu::query()->create(['key' => 'primary', 'name' => 'Menu chính', 'is_active' => true]);

        $this->page = Page::query()->create([
            'title' => ['vi' => 'Giới thiệu', 'en' => 'About'],
            'slug' => 'gioi-thieu',
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

    public function test_a_content_page_renders_the_menu_with_a_link_to_itself(): void
    {
        $this->item([
            'label' => ['vi' => 'Giới thiệu'],
            'type' => MenuItem::TYPE_PAGE,
            'page_id' => $this->page->id,
            'url' => null,
        ]);

        $response = $this->get('/vi/pages/gioi-thieu');

        $response->assertOk();
        $response->assertSee('client-nav', false);
        $response->assertSee('Giới thiệu');
        $response->assertSee('href="'.route('client.pages.show', ['locale' => 'vi', 'slug' => 'gioi-thieu']).'"', false);
    }

    public function test_nested_items_render_as_a_nested_list(): void
    {
        $parent = $this->item(['label' => ['vi' => 'Cha']]);
        $this->item(['label' => ['vi' => 'Con'], 'parent_id' => $parent->id, 'url' => '/con']);

        $this->get('/vi/pages/gioi-thieu')
            ->assertOk()
            ->assertSee('client-nav-level-0', false)
            ->assertSee('client-nav-level-1', false);
    }

    public function test_hidden_items_and_hidden_menus_do_not_reach_the_storefront(): void
    {
        $this->item(['label' => ['vi' => 'Ẩn mục'], 'is_active' => false]);
        $this->item(['label' => ['vi' => 'Hiện mục']]);

        $this->get('/vi/pages/gioi-thieu')
            ->assertOk()
            ->assertSee('Hiện mục')
            ->assertDontSee('Ẩn mục');

        $this->menu->update(['is_active' => false]);

        $this->get('/vi/pages/gioi-thieu')
            ->assertOk()
            ->assertDontSee('Hiện mục');
    }

    public function test_an_item_without_a_resolvable_target_renders_as_text_not_a_broken_link(): void
    {
        $this->item([
            'label' => ['vi' => 'Danh mục chưa có trang'],
            'type' => MenuItem::TYPE_CATEGORY,
            'category_id' => null,
            'url' => null,
        ]);

        $response = $this->get('/vi/pages/gioi-thieu')->assertOk();

        $response->assertSee('Danh mục chưa có trang');
        $response->assertSee('<span>Danh mục chưa có trang</span>', false);
    }

    public function test_the_navigation_is_marked_uneditable_for_the_inline_page_editor(): void
    {
        $this->item(['label' => ['vi' => 'Mục']]);

        $this->get('/vi/pages/gioi-thieu')
            ->assertOk()
            ->assertSee('<header class="client-header" contenteditable="false">', false);
    }

    public function test_labels_follow_the_requested_locale(): void
    {
        $this->item(['label' => ['vi' => 'Giới thiệu', 'en' => 'About us'], 'url' => '/about']);

        $this->get('/vi/pages/gioi-thieu')->assertOk()->assertSee('Giới thiệu');
        $this->get('/en/pages/gioi-thieu')->assertOk()->assertSee('About us');
    }

    public function test_public_api_returns_the_same_tree(): void
    {
        $parent = $this->item([
            'label' => ['vi' => 'Giới thiệu'],
            'type' => MenuItem::TYPE_PAGE,
            'page_id' => $this->page->id,
            'url' => null,
        ]);
        $this->item(['label' => ['vi' => 'Lịch sử'], 'parent_id' => $parent->id, 'url' => '/lich-su']);

        $response = $this->getJson('/api/public/menus/primary')->assertOk();

        // The endpoint negotiates its own locale, so the expected page URL is
        // built from the locale it reports rather than a hard-coded one.
        $locale = $response->json('meta.locale');

        $response
            ->assertJsonPath('data.key', 'primary')
            ->assertJsonPath('data.items.0.label', 'Giới thiệu')
            ->assertJsonPath('data.items.0.url', route('client.pages.show', ['locale' => $locale, 'slug' => 'gioi-thieu']))
            ->assertJsonPath('data.items.0.children.0.label', 'Lịch sử')
            ->assertJsonPath('data.items.0.children.0.url', '/lich-su');
    }

    public function test_public_api_404s_for_an_unknown_or_hidden_menu(): void
    {
        $this->getJson('/api/public/menus/khong-ton-tai')->assertNotFound();

        $this->menu->update(['is_active' => false]);
        $this->getJson('/api/public/menus/primary')->assertNotFound();
    }

    public function test_public_menu_endpoint_requires_the_menu_feature(): void
    {
        FeatureSetting::query()->where('feature_code', 'menu')->update(['is_enabled' => false]);

        $this->getJson('/api/public/menus/primary')->assertForbidden();
    }
}
