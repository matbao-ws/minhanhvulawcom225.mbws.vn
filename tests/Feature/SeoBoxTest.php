<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\PageContentService;
use App\Services\Seo\SeoMetaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Products and policy pages have the post form's SEO box (admin.shared.seo-box): focus
 * keywords and score, share fields, canonical and robots, FAQ schema, used on the storefront.
 */
class SeoBoxTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalog', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        $this->admin = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Admin', 'permissions' => ['*']])->id]);
        // What a theme's routes/theme.php declares for its product page.
        Route::middleware(['web', 'setLocale'])->get('{locale}/san-pham/{slug}', fn () => 'theme page')->name('client.products.show');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_the_product_and_page_forms_show_the_seo_box(): void
    {
        $this->actingAs($this->admin)->get('/vi/admin/products/create')->assertOk()
            ->assertSee('data-seo-box', false)
            ->assertSee('name="meta_title[vi]"', false)
            ->assertSee('name="og_title[vi]"', false)
            ->assertSee('seo-box.js', false)
            // Products get Product schema on their own: no type to choose.
            ->assertDontSee('name="schema_type"', false);

        $this->actingAs($this->admin)->get('/vi/admin/pages/create')->assertOk()
            ->assertSee('data-seo-box', false)
            ->assertSee('id="page-title-vi"', false);
    }

    public function test_a_product_saves_its_seo_box_and_is_scored(): void
    {
        $this->actingAs($this->admin)->post('/vi/admin/products', [
            'name' => ['vi' => 'Giày chạy bộ Nike Pegasus'], 'price' => 2500000, 'stock_quantity' => 3, 'is_active' => 1,
            'description' => ['vi' => '<p>Giày chạy bộ êm.</p>'],
            'meta_title' => ['vi' => 'Giày chạy bộ Nike Pegasus 41 chính hãng'],
            'seo_keys' => 'giày chạy bộ, nike pegasus',
            'og_title' => ['vi' => 'Giảm 20% giày chạy bộ'], 'og_image' => url('/storage/products/share.jpg'),
            'canonical_url' => 'https://brand.example/pegasus', 'robots_index' => '0', 'robots_follow' => '1', 'schema_faq' => '1',
        ])->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();
        $this->assertSame('giày chạy bộ, nike pegasus', $product->seo_keys);
        $this->assertSame('Giảm 20% giày chạy bộ', $product->getTranslation('og_title', 'vi'));
        $this->assertSame('/storage/products/share.jpg', $product->getRawOriginal('og_image'));
        $this->assertFalse($product->robots_index);
        $this->assertGreaterThan(0, $product->seo_score, 'scored with the post analyzer');

        // An import or API call that does not know these fields leaves them alone.
        app(ProductService::class)->update($product, ['name' => ['vi' => 'Giày chạy bộ Nike Pegasus 41'], 'price' => 2400000]);
        $this->assertSame('giày chạy bộ, nike pegasus', $product->fresh()->seo_keys);
        $this->assertFalse($product->fresh()->robots_index);
    }

    public function test_the_storefront_uses_a_products_seo_box(): void
    {
        $product = Product::query()->create([
            'name' => ['vi' => 'Máy bơm'], 'slug' => 'may-bom', 'price' => 1000000, 'is_active' => true,
            'meta_title' => ['vi' => 'Máy bơm nước gia đình'],
            'description' => ['vi' => '<p>Bơm êm.</p><h2>Câu hỏi thường gặp</h2><h3>Bảo hành bao lâu?</h3><p>24 tháng.</p>'],
            'og_title' => ['vi' => 'Máy bơm giảm giá'], 'og_image' => '/storage/products/share.jpg',
            'robots_index' => false, 'canonical_url' => 'https://brand.example/may-bom',
        ]);
        $this->app['env'] = 'production';
        app()->setLocale('vi');

        $seo = app(SeoMetaFactory::class)->forProduct($product);

        $this->assertSame('Máy bơm nước gia đình', $seo->title);
        $this->assertSame('Máy bơm giảm giá', $seo->ogTitle());
        $this->assertStringEndsWith('/storage/products/share.jpg', (string) $seo->image);
        $this->assertSame('noindex, follow', $seo->robots());
        $this->assertSame('https://brand.example/may-bom', $seo->canonical);
        $this->assertNotNull(collect($seo->structuredData)->firstWhere('@type', 'Product'));
        $this->assertSame('Bảo hành bao lâu?', collect($seo->structuredData)->firstWhere('@type', 'FAQPage')['mainEntity'][0]['name']);
    }

    public function test_a_page_saves_its_seo_box_is_scored_and_rescored_when_edited_on_the_storefront(): void
    {
        $this->actingAs($this->admin)->post('/vi/admin/pages', [
            'title' => ['vi' => 'Chính sách đổi trả'], 'slug' => ['vi' => 'doi-tra'], 'is_active' => 1,
            'meta_title' => ['vi' => 'Chính sách đổi trả hàng'],
            'published_html' => ['vi' => '<p>Đổi trả trong 7 ngày.</p>'],
            'seo_keys' => 'đổi trả', 'og_description' => ['vi' => 'Đổi trả miễn phí 7 ngày.'], 'robots_follow' => '0',
        ])->assertRedirect();

        $page = Page::query()->latest('id')->firstOrFail();
        $this->assertSame('đổi trả', $page->seo_keys);
        $this->assertSame('Đổi trả miễn phí 7 ngày.', $page->getTranslation('og_description', 'vi'));
        $this->assertFalse($page->robots_follow);
        $before = $page->seo_score;
        $this->assertNotNull($before);

        // Edited on the storefront: the score follows the new text.
        app(PageContentService::class)->updateLocale($page, 'vi', '<h2>Đổi trả hàng</h2><p>Đổi trả trong 7 ngày, đổi trả miễn phí.</p><p><img src="/a.jpg" alt="đổi trả"></p>', null);
        $this->assertGreaterThan($before, $page->fresh()->seo_score);

        app()->setLocale('vi');
        $seo = app(SeoMetaFactory::class)->forPage($page->fresh());
        $this->assertSame('Chính sách đổi trả hàng', $seo->title);
        $this->assertSame('Đổi trả miễn phí 7 ngày.', $seo->ogDescription());
        $this->assertFalse($seo->follow);
    }
}
