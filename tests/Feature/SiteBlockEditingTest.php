<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Role;
use App\Models\SiteBlock;
use App\Models\User;
use App\Services\SiteContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Region-level editing of static storefront content.
 */
class SiteBlockEditingTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        (new \Database\Seeders\PermissionSeeder())->run();
        foreach (['catalog', 'cms_page', 'menu'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }

        $this->category = Category::query()->create([
            'name' => ['vi' => 'Điện thoại'],
            'slug' => 'dien-thoai',
            'is_active' => true,
        ]);
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

    private function site(): SiteContentService
    {
        // Scoped, so the request-level cache has to be dropped between checks.
        app(SiteContentService::class)->forget();

        return app(SiteContentService::class);
    }

    public function test_a_region_renders_its_blade_default_until_it_is_edited(): void
    {
        $this->get('/vi/danh-muc/dien-thoai')
            ->assertOk()
            ->assertSee('Danh mục này chưa có sản phẩm nào.');

        $this->assertDatabaseCount('site_blocks', 0);
    }

    public function test_saving_a_region_overrides_the_default(): void
    {
        $this->actingWith(['pages.update']);

        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'catalog.category.empty',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'vi',
            'value' => 'Sắp có hàng mới, quay lại sau nhé.',
        ])->assertOk()->assertJsonPath('data.value', 'Sắp có hàng mới, quay lại sau nhé.');

        $this->get('/vi/danh-muc/dien-thoai')
            ->assertOk()
            ->assertSee('Sắp có hàng mới, quay lại sau nhé.')
            ->assertDontSee('Danh mục này chưa có sản phẩm nào.');
    }

    public function test_text_regions_are_stripped_of_markup(): void
    {
        $this->actingWith(['pages.update']);

        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'catalog.category.empty',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'vi',
            'value' => 'Xin chào <script>alert(1)</script><b>bạn</b>',
        ])->assertOk()->assertJsonPath('data.value', 'Xin chào alert(1)bạn');
    }

    public function test_saving_one_locale_leaves_the_others_untouched(): void
    {
        $this->actingWith(['pages.update']);

        foreach (['vi' => 'Trống', 'en' => 'Empty'] as $locale => $value) {
            $this->patchJson('/vi/admin/site-blocks', [
                'key' => 'catalog.category.empty',
                'type' => SiteBlock::TYPE_TEXT,
                'content_locale' => $locale,
                'value' => $value,
            ])->assertOk();
        }

        $block = SiteBlock::query()->firstOrFail();
        $this->assertSame(['vi' => 'Trống', 'en' => 'Empty'], $block->rawTranslations());
    }

    /**
     * An empty string is a real value — it hides the region — and must not be
     * confused with "never edited".
     */
    public function test_an_emptied_region_is_hidden_rather_than_falling_back(): void
    {
        $this->actingWith(['pages.update']);

        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'catalog.category.empty',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'vi',
            'value' => '',
        ])->assertOk()->assertJsonPath('data.cleared', true);

        $this->assertTrue($this->site()->isCleared('catalog.category.empty', 'vi'));
        $this->assertNull($this->site()->value('catalog.category.empty', 'vi'));

        // A guest sees neither the override nor the Blade default.
        $this->get('/vi/danh-muc/dien-thoai')
            ->assertOk()
            ->assertDontSee('Danh mục này chưa có sản phẩm nào.');
    }

    public function test_restoring_drops_the_override_and_the_row(): void
    {
        $this->actingWith(['pages.update']);

        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'catalog.category.empty',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'vi',
            'value' => 'Tuỳ chỉnh',
        ])->assertOk();

        $this->deleteJson('/vi/admin/site-blocks', [
            'key' => 'catalog.category.empty',
            'content_locale' => 'vi',
        ])->assertOk();

        $this->assertDatabaseCount('site_blocks', 0);
        $this->get('/vi/danh-muc/dien-thoai')->assertOk()->assertSee('Danh mục này chưa có sản phẩm nào.');
    }

    public function test_an_unchanged_value_writes_nothing_and_records_no_revision(): void
    {
        $this->actingWith(['pages.update']);
        $payload = [
            'key' => 'catalog.category.empty',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'vi',
            'value' => 'Cùng một giá trị',
        ];

        $this->patchJson('/vi/admin/site-blocks', $payload)->assertOk();
        $this->patchJson('/vi/admin/site-blocks', $payload)->assertOk();

        // The first save of a new block has nothing to snapshot, and the second
        // changed nothing at all.
        $this->assertDatabaseCount('site_block_revisions', 0);
    }

    public function test_revisions_are_coalesced_within_ten_minutes(): void
    {
        $this->actingWith(['pages.update']);

        foreach (['Một', 'Hai', 'Ba'] as $value) {
            $this->patchJson('/vi/admin/site-blocks', [
                'key' => 'catalog.category.empty',
                'type' => SiteBlock::TYPE_TEXT,
                'content_locale' => 'vi',
                'value' => $value,
            ])->assertOk();
        }

        // First save: nothing to snapshot. Second: one checkpoint. Third: reuses it.
        $this->assertDatabaseCount('site_block_revisions', 1);

        $this->travel(11)->minutes();
        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'catalog.category.empty',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'vi',
            'value' => 'Bốn',
        ])->assertOk();

        $this->assertDatabaseCount('site_block_revisions', 2);
    }

    public function test_an_unknown_key_shape_is_rejected(): void
    {
        $this->actingWith(['pages.update']);

        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'Không Hợp Lệ',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'vi',
            'value' => 'x',
        ])->assertUnprocessable();
    }

    public function test_image_regions_need_the_media_permission(): void
    {
        $this->actingWith(['pages.update']);

        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'catalog.category.banner',
            'type' => SiteBlock::TYPE_IMAGE,
            'content_locale' => 'vi',
            'value' => 'https://cdn.example.test/a.jpg',
        ])->assertForbidden();
    }

    public function test_guests_and_customers_cannot_write_regions(): void
    {
        $payload = [
            'key' => 'catalog.category.empty',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'vi',
            'value' => 'x',
        ];

        $this->patchJson('/vi/admin/site-blocks', $payload)->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role_id' => null]));
        $this->patchJson('/vi/admin/site-blocks', $payload)->assertForbidden();
    }

    public function test_guests_receive_no_region_hooks(): void
    {
        $html = $this->get('/vi/danh-muc/dien-thoai')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-block-key', $html);
    }

    public function test_an_authorized_admin_receives_region_hooks(): void
    {
        $this->actingWith(['pages.update']);

        $this->get('/vi/danh-muc/dien-thoai')
            ->assertOk()
            ->assertSee('data-block-key="catalog.category.empty"', false);
    }

    /**
     * The point of the skill's rule: a product name is edited on the product
     * screen, never twice.
     */
    public function test_database_driven_cards_carry_no_edit_hooks(): void
    {
        Product::query()->create([
            'name' => ['vi' => 'iPhone 15'],
            'slug' => 'iphone-15',
            'sku' => 'IP-15',
            'price' => 1000,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);
        $this->actingWith(['pages.update', 'media.view']);

        $html = $this->get('/vi/danh-muc/dien-thoai')->assertOk()->getContent();

        $this->assertStringContainsString('iPhone 15', $html);
        // Match the attribute, not the bare token: the editor stylesheet mentions
        // [data-block-key] on every admin page.
        $this->assertStringNotContainsString('data-block-key=', $html);
    }

    public function test_the_blog_category_page_lists_posts_and_marks_only_static_text(): void
    {
        $category = PostCategory::query()->create([
            'name' => ['vi' => 'Tin tức'],
            'slug' => 'tin-tuc',
            'is_active' => true,
        ]);
        Post::query()->create([
            'category_id' => $category->id,
            'title' => ['vi' => 'Bài viết một'],
            'slug' => 'bai-viet-mot',
            'content' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
        $this->actingWith(['pages.update']);

        $html = $this->get('/vi/chuyen-muc/tin-tuc')->assertOk()->getContent();

        $this->assertStringContainsString('Bài viết một', $html);
        $this->assertStringNotContainsString('data-block-key=', $html);
    }

    public function test_inactive_category_pages_are_not_reachable(): void
    {
        $this->category->update(['is_active' => false]);

        $this->get('/vi/danh-muc/dien-thoai')->assertNotFound();
    }
}
