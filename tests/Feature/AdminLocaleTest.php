<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Language;
use App\Models\Role;
use App\Models\SiteBlock;
use App\Models\User;
use App\Services\LanguageRegistry;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A language added for the storefront (ja) gets content tabs and /ja/… pages,
 * but the admin interface stays in the languages lang/ ships (vi, en).
 */
class AdminLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new PermissionSeeder)->run();
        foreach (['catalog', 'cms_page', 'menu', 'multilingual'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }

        Language::query()->updateOrCreate(['code' => 'ja'], [
            'name' => 'Japanese',
            'native_name' => '日本語',
            'regional' => 'ja_JP',
            'is_active' => true,
            'is_default' => false,
            'is_content_fallback' => false,
            'sort_order' => 20,
        ]);
        app(LanguageRegistry::class)->forget();
    }

    protected function tearDown(): void
    {
        app(LanguageRegistry::class)->forget();

        parent::tearDown();
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

    public function test_storefront_language_is_content_only(): void
    {
        $languages = app(LanguageRegistry::class);

        $this->assertContains('ja', $languages->codes());
        $this->assertSame(['vi', 'en'], $languages->adminCodes());
        $this->assertSame('en', $languages->adminLocale('ja'));
        $this->assertSame('vi', $languages->adminLocale('vi'));
    }

    public function test_opening_an_admin_page_in_a_storefront_language_redirects_to_the_admin_fallback(): void
    {
        $this->get('/ja/admin')->assertRedirect(url('/en/admin'));
        $this->get('/ja/admin/products?page=2&q=ao')->assertRedirect(url('/en/admin/products').'?page=2&q=ao');
        $this->get('/ja/admin/login')->assertRedirect(url('/en/admin/login'));
    }

    public function test_falls_back_to_the_default_language_when_english_is_off(): void
    {
        Language::query()->where('code', 'en')->update(['is_active' => false]);
        app(LanguageRegistry::class)->forget();

        $this->get('/ja/admin')->assertRedirect(url('/vi/admin'));
    }

    public function test_a_bare_admin_link_opens_in_the_last_admin_language_else_the_shop_default(): void
    {
        // Nothing chosen yet: the shop's default language (vi), not admin_fallback_locale (en).
        $this->get('/admin')->assertRedirect('/vi/admin');

        $this->get('/en/admin/login')->assertOk();
        $this->get('/admin')->assertRedirect('/en/admin');

        // A storefront-only language never becomes the admin's.
        $this->get('/ja/admin/login');
        $this->get('/admin')->assertRedirect('/en/admin');

        $this->flushSession();
        $this->get('/vi/admin/login')->assertOk();
        $this->get('/admin')->assertRedirect('/vi/admin');

        $this->assertSame('vi', app(LanguageRegistry::class)->adminEntryLocale());
        $this->assertSame('en', app(LanguageRegistry::class)->adminEntryLocale('en'));
        $this->assertSame('vi', app(LanguageRegistry::class)->adminEntryLocale('ja'));
    }

    public function test_an_unknown_locale_is_still_not_found(): void
    {
        $this->get('/fr/admin')->assertNotFound();
    }

    public function test_storefront_pages_still_render_in_the_storefront_language(): void
    {
        Category::query()->create([
            'name' => ['vi' => 'Điện thoại', 'ja' => '携帯電話'],
            'slug' => 'dien-thoai',
            'is_active' => true,
        ]);

        $this->get('/ja/danh-muc/dien-thoai')->assertOk();
    }

    public function test_the_storefront_editor_keeps_saving_in_the_page_language(): void
    {
        $this->actingWith(['pages.update']);

        // Writes are never redirected: a redirect would turn the PATCH into a GET.
        $this->patchJson('/ja/admin/site-blocks', [
            'key' => 'catalog.category.empty',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'ja',
            'value' => '商品はまだありません。',
        ])->assertOk()->assertJsonPath('data.value', '商品はまだありません。');

        $block = SiteBlock::query()->where('key', 'catalog.category.empty')->firstOrFail();
        $this->assertSame('商品はまだありません。', $block->rawTranslations()['ja'] ?? null);

        // History reads the content locale from the URL, so a JSON GET keeps it too.
        $this->getJson('/ja/admin/site-blocks/revisions?key=catalog.category.empty')->assertOk();
    }

    public function test_admin_bar_on_a_storefront_language_page_links_into_the_translated_admin(): void
    {
        Category::query()->create([
            'name' => ['vi' => 'Điện thoại'],
            'slug' => 'dien-thoai',
            'is_active' => true,
        ]);
        $this->actingWith(['pages.update']);

        $this->get('/ja/danh-muc/dien-thoai')
            ->assertOk()
            ->assertSee('href="'.url('/en/admin').'"', false);
    }
}
