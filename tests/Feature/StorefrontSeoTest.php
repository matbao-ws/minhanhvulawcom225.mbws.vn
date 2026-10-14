<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\ProductVariant;
use App\Models\ProjectSetting;
use App\Models\Review;
use App\Services\Catalog\ProductQueryService;
use App\Services\LanguageRegistry;
use App\Services\Seo\SeoMetaFactory;
use App\Services\Seo\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StorefrontSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cms_page', 'catalog'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
    }

    public function test_a_page_carries_title_description_canonical_and_social_tags(): void
    {
        $page = $this->page([
            'meta_title' => ['vi' => 'Chính sách đổi trả | Shop'],
            'meta_description' => ['vi' => "<p>Đổi trả trong\n 7 ngày &amp; miễn phí.</p>"],
        ]);
        $url = route('client.pages.show', ['locale' => 'vi', 'slug' => $page->canonicalSlug('vi')]);

        $this->get('/chinh-sach/'.$page->canonicalSlug('vi'))
            ->assertOk()
            ->assertSee('<title>Chính sách đổi trả | Shop</title>', false)
            // Plain text, whitespace collapsed, entities decoded once and re-escaped once.
            ->assertSee('<meta name="description" content="Đổi trả trong 7 ngày &amp; miễn phí.">', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertSee('<meta property="og:url" content="'.$url.'">', false)
            ->assertSee('<meta property="og:title" content="Chính sách đổi trả | Shop">', false)
            ->assertSee('"@type":"Organization"', false);
    }

    public function test_only_production_asks_to_be_indexed(): void
    {
        $page = $this->page();
        $path = '/chinh-sach/'.$page->canonicalSlug('vi');

        // Tests run as "testing": like staging, a copy of the shop must stay out of search.
        $this->get($path)->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->app['env'] = 'production';
        // Indexable pages also allow a long snippet and a large image preview (Search, Discover).
        $this->get($path)->assertSee('<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">', false);
    }

    public function test_every_active_language_is_declared_as_an_alternate(): void
    {
        $codes = app(LanguageRegistry::class)->codes();
        $this->assertGreaterThan(1, count($codes), 'precondition: the test install has more than one language');

        $page = $this->page();
        $response = $this->get('/chinh-sach/'.$page->canonicalSlug('vi'))->assertOk();

        foreach ($codes as $code) {
            $response->assertSee('<link rel="alternate" hreflang="'.$code.'" href="'.route('client.pages.show', ['locale' => $code, 'slug' => $page->canonicalSlug($code)]).'">', false);
        }

        $default = app(LanguageRegistry::class)->defaultLocale();
        $response->assertSee('hreflang="x-default" href="'.route('client.pages.show', ['locale' => $default, 'slug' => $page->canonicalSlug($default)]).'"', false);
    }

    public function test_page_two_of_a_listing_is_its_own_canonical_url(): void
    {
        $this->category('ao-thun');
        $url = route('client.categories.show', ['locale' => 'vi', 'slug' => 'ao-thun']);

        $this->get('/danh-muc/ao-thun?page=2&sort=price')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$url.'?page=2">', false);
    }

    public function test_a_subcategory_gets_breadcrumbs_through_its_visible_parents(): void
    {
        $parent = $this->category('thoi-trang', ['name' => ['vi' => 'Thời trang']]);
        $this->category('ao-thun', ['name' => ['vi' => 'Áo thun'], 'parent_id' => $parent->id]);

        $html = $this->get('/danh-muc/ao-thun')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('"position":1,"name":"Thời trang","item":"'.route('client.categories.show', ['locale' => 'vi', 'slug' => 'thoi-trang']).'"', $html);
        $this->assertStringContainsString('"position":2,"name":"Áo thun"', $html);

        // A parent the visitor cannot open is not linked.
        $parent->update(['is_draft' => true]);
        $this->assertStringNotContainsString('BreadcrumbList', $this->get('/danh-muc/ao-thun')->getContent());
    }

    public function test_structured_data_cannot_be_broken_out_of_by_the_shop_name(): void
    {
        ProjectSetting::query()->create(['setting_key' => 'shop_name', 'setting_value' => 'Shop</script><script>alert(1)</script>']);
        $page = $this->page();

        $html = $this->get('/chinh-sach/'.$page->canonicalSlug('vi'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        // "<" and ">" leave as < / > inside the JSON-LD.
        $this->assertStringContainsString(trim(json_encode('Shop</script>', JSON_HEX_TAG | JSON_UNESCAPED_SLASHES), '"'), $html);
    }

    public function test_embed_code_from_settings_is_printed_in_head_and_before_body_end(): void
    {
        ProjectSetting::query()->create(['setting_key' => 'embed_header', 'setting_value' => '<meta name="google-site-verification" content="abc123">']);
        ProjectSetting::query()->create(['setting_key' => 'embed_footer', 'setting_value' => '<script>window.chatWidget = true;</script>']);
        $page = $this->page();

        $html = $this->get('/chinh-sach/'.$page->canonicalSlug('vi'))->assertOk()->getContent();

        $head = substr($html, 0, strpos($html, '</head>'));
        $this->assertStringContainsString('<meta name="google-site-verification" content="abc123">', $head);
        $this->assertStringContainsString('<script>window.chatWidget = true;</script>', substr($html, strpos($html, '<body')));
    }

    public function test_a_theme_page_that_passes_no_seo_still_gets_head_tags_from_its_sections(): void
    {
        $dir = sys_get_temp_dir().'/seo-theme-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/page.blade.php', "@extends('client.layouts.app')\n@section('title', 'Áo & quần')\n@section('meta_description', 'Hàng \"mới\" về')\n@section('content')<p>x</p>@endsection\n");
        view()->addNamespace('seotheme', $dir);

        try {
            $html = view('seotheme::page')->render();
        } finally {
            @unlink($dir.'/page.blade.php');
            @rmdir($dir);
        }

        // Escaped exactly once, although the section text arrives already escaped.
        $this->assertStringContainsString('<title>Áo &amp; quần</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Hàng &quot;mới&quot; về">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
    }

    public function test_robots_txt_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText("User-agent: *\nDisallow: /", false);

        $this->app['env'] = 'production';
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /*/admin', $body);
        $this->assertStringContainsString('Sitemap: '.url('sitemap.xml'), $body);
        $this->assertStringNotContainsString("Disallow: /\n", $body);
    }

    public function test_sitemap_lists_only_what_a_visitor_can_open_in_every_language(): void
    {
        $live = $this->page(['slug' => 'doi-tra']);
        $this->page(['slug' => 'nhap', 'is_active' => false]);
        $this->page(['slug' => 'sap-dang', 'published_at' => now()->addDay()]);
        $this->category('ao-thun');
        $this->category('dang-soan', ['is_draft' => true]);

        $xml = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap is well-formed XML');

        foreach (app(LanguageRegistry::class)->codes() as $code) {
            $this->assertStringContainsString('<loc>'.route('client.pages.show', ['locale' => $code, 'slug' => $live->canonicalSlug($code)]).'</loc>', $xml);
            $this->assertStringContainsString('hreflang="'.$code.'"', $xml);
        }

        $this->assertStringContainsString('/danh-muc/ao-thun</loc>', $xml);
        $this->assertStringNotContainsString('nhap', $xml);
        $this->assertStringNotContainsString('sap-dang', $xml);
        $this->assertStringNotContainsString('dang-soan', $xml);
        // No storefront route for products or posts until a theme adds one.
        $this->assertStringNotContainsString('/san-pham/', $xml);
    }

    /**
     * A record opened in a language it has no text in shows the fallback's text: that URL is
     * not a translation, so it is neither declared (hreflang, sitemap) nor its own canonical.
     */
    public function test_an_untranslated_record_declares_only_the_languages_it_has(): void
    {
        $page = $this->page(['slug' => 'chi-tieng-viet', 'title' => ['vi' => 'Chỉ tiếng Việt']]);
        $vi = route('client.pages.show', ['locale' => 'vi', 'slug' => $page->canonicalSlug('vi')]);
        $en = route('client.pages.show', ['locale' => 'en', 'slug' => $page->canonicalSlug('en')]);

        $this->get($vi)->assertOk()->assertDontSee('hreflang="en"', false);
        $this->get($en)->assertOk()->assertSee('<link rel="canonical" href="'.$vi.'">', false);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>'.$vi.'</loc>', $xml);
        $this->assertStringNotContainsString('<loc>'.$en.'</loc>', $xml);
    }

    /** A theme's own pages (routes/theme.php) are crawlable too, except private ones. */
    public function test_the_sitemap_lists_the_theme_pages_but_not_private_ones(): void
    {
        $locales = implode('|', app(LanguageRegistry::class)->codes());
        Route::middleware(['web', 'setLocale'])->prefix('{locale}')->where(['locale' => $locales])->group(function (): void {
            Route::get('/', fn () => 'home')->name('client.home');
            Route::get('gioi-thieu', fn () => 'about')->name('client.about');
            Route::get('gio-hang', fn () => 'cart')->name('client.cart');
            Route::get('tai-khoan/don-hang', fn () => 'orders')->name('client.account.orders');
            Route::get('noi-bo', fn () => 'internal')->name('client.internal')->defaults('sitemap', false);
            Route::redirect('cu', '/vi/gioi-thieu')->name('client.old');
        });
        Route::getRoutes()->refreshNameLookups();

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('client.about', ['locale' => 'vi']).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('client.about', ['locale' => 'en']).'</loc>', $xml);
        $this->assertStringContainsString('hreflang="en" href="'.route('client.about', ['locale' => 'en']).'"', $xml);
        foreach (['gio-hang', 'don-hang', 'noi-bo', '/cu<'] as $private) {
            $this->assertStringNotContainsString($private, $xml);
        }
        // Home first.
        $this->assertStringStartsWith('<loc>'.route('client.home', ['locale' => 'vi']).'</loc>', trim(explode('<url>', $xml)[1]));
    }

    public function test_sitemap_follows_feature_switches(): void
    {
        $this->category('ao-thun');
        FeatureSetting::query()->where('feature_code', 'catalog')->update(['is_enabled' => false]);
        SitemapService::flush();

        $this->assertStringNotContainsString('/danh-muc/', $this->get('/sitemap.xml')->assertOk()->getContent());
    }

    public function test_a_theme_product_page_gets_price_availability_reviews_and_breadcrumbs(): void
    {
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'review'], ['is_enabled' => true]);
        $this->themeRoute('san-pham', 'client.products.show');
        $parent = $this->category('ong-nhua');
        $category = $this->category('ong-upvc', ['parent_id' => $parent->id]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => ['vi' => 'Ống uPVC 21mm'], 'slug' => 'ong-upvc-21',
            'sku' => 'UPVC21', 'short_description' => ['vi' => '<p>Ống chịu áp lực.</p>'],
            'price' => 30000, 'stock_quantity' => 0, 'manage_stock' => true, 'is_active' => true,
        ]);
        $group = ProductOptionGroup::query()->create(['product_id' => $product->id, 'name' => ['vi' => 'Dài'], 'code' => 'length']);
        $variant = fn (string $sku, ?float $price, int $stock, bool $active = true) => ProductVariant::query()->create([
            'product_id' => $product->id, 'name' => $sku, 'sku' => $sku, 'option_signature' => $sku,
            'price' => $price, 'stock_quantity' => $stock, 'is_active' => $active,
        ]);
        $variant('4M', 32000, 0);
        // No price of its own: sells at the product's, like checkout.
        $short = $variant('2M', null, 3);
        // Not on sale: neither its price nor its stock counts.
        $variant('6M', 99000, 9, false);
        foreach ([[5, true], [4, true], [1, false]] as [$rating, $visible]) {
            Review::query()->create(['product_id' => $product->id, 'customer_name' => 'Khách', 'customer_email' => 'khach@example.com', 'rating' => $rating, 'comment' => 'x', 'is_visible' => $visible]);
        }
        $this->assertTrue($group->exists);

        app()->setLocale('vi');
        $seo = app(SeoMetaFactory::class)->forProduct(app(ProductQueryService::class)->findActiveDetail('ong-upvc-21'));
        $node = collect($seo->structuredData)->firstWhere('@type', 'Product');

        $this->assertSame('product', $seo->type);
        $this->assertSame('Ống chịu áp lực.', $seo->description);
        $this->assertSame(['@type' => 'AggregateOffer', 'url' => $seo->canonical, 'lowPrice' => '30000', 'highPrice' => '32000',
            'offerCount' => 2, 'priceCurrency' => 'VND', 'availability' => 'https://schema.org/InStock'], $node['offers']);
        // Only the reviews visitors can read.
        $this->assertSame([4.5, 2], [$node['aggregateRating']['ratingValue'], $node['aggregateRating']['reviewCount']]);
        $crumbs = collect($seo->structuredData)->firstWhere('@type', 'BreadcrumbList')['itemListElement'];
        $this->assertSame(['ong-nhua', 'ong-upvc', 'Ống uPVC 21mm'], array_column($crumbs, 'name'));

        // Nothing left to sell: still listed, as out of stock.
        $short->update(['stock_quantity' => 0]);
        $seo = app(SeoMetaFactory::class)->forProduct(app(ProductQueryService::class)->findActiveDetail('ong-upvc-21'));
        $this->assertSame('https://schema.org/OutOfStock', collect($seo->structuredData)->firstWhere('@type', 'Product')['offers']['availability']);

        $html = view('core.partials.seo-head', ['seo' => $seo])->render();
        $this->assertStringContainsString('<meta property="og:type" content="product">', $html);
        $this->assertStringContainsString('"@type":"Product"', $html);
    }

    public function test_a_contact_for_price_product_has_no_offer(): void
    {
        $product = Product::query()->create(['name' => ['vi' => 'Máy bơm'], 'slug' => 'may-bom', 'price' => 0, 'is_active' => true]);

        app()->setLocale('vi');
        // No theme route yet: the tags still come, at the URL the page was opened on.
        $node = collect(app(SeoMetaFactory::class)->forProduct($product)->structuredData)->firstWhere('@type', 'Product');

        $this->assertSame('Máy bơm', $node['name']);
        $this->assertArrayNotHasKey('offers', $node);
    }

    public function test_a_post_shares_its_own_title_and_image_and_publishes_its_type_and_faq(): void
    {
        $this->themeRoute('bai-viet', 'client.posts.show');
        $post = Post::query()->create([
            'title' => ['vi' => 'Cách chọn ống nước'], 'slug' => 'cach-chon-ong', 'is_active' => true,
            'content' => ['vi' => '<p>Nội dung.</p><h2>Câu hỏi thường gặp</h2><h3>Ống nào bền nhất?</h3><p>Ống uPVC.</p><h3>Bao lâu thay?</h3><p>Mười năm.</p><h2>Kết luận</h2><h3>Không phải câu hỏi</h3><p>x</p>'],
            'seo_title' => ['vi' => 'Chọn ống nước đúng cách'], 'seo_description' => ['vi' => 'Mô tả cho Google.'],
            'og_title' => ['vi' => 'Đừng mua ống nước trước khi đọc bài này'], 'og_description' => ['vi' => 'Mô tả khi chia sẻ.'],
            'og_image' => '/storage/posts/share.jpg', 'image_url' => '/storage/posts/cover.jpg',
            'schema_type' => 'BlogPosting',
        ]);

        app()->setLocale('vi');
        $seo = app(SeoMetaFactory::class)->forPost($post);

        // Google keeps the SEO title; Facebook, Zalo and X get the post's own.
        $this->assertSame('Chọn ống nước đúng cách', $seo->title);
        $this->assertSame('Đừng mua ống nước trước khi đọc bài này', $seo->ogTitle());
        $this->assertSame('Mô tả khi chia sẻ.', $seo->ogDescription());
        $this->assertStringEndsWith('/storage/posts/share.jpg', (string) $seo->image);
        $this->assertSame('summary_large_image', $seo->twitterCard());

        $this->assertNotNull(collect($seo->structuredData)->firstWhere('@type', 'BlogPosting'));
        $faq = collect($seo->structuredData)->firstWhere('@type', 'FAQPage');
        $this->assertSame(['Ống nào bền nhất?', 'Bao lâu thay?'], array_column($faq['mainEntity'], 'name'));
        $this->assertSame('Ống uPVC.', $faq['mainEntity'][0]['acceptedAnswer']['text']);

        // Switched off, and without its own share fields, it falls back to the SEO ones.
        $post->update(['schema_faq' => false, 'og_title' => [], 'og_description' => [], 'og_image' => null]);
        $seo = app(SeoMetaFactory::class)->forPost($post->fresh());
        $this->assertNull(collect($seo->structuredData)->firstWhere('@type', 'FAQPage'));
        $this->assertSame('Chọn ống nước đúng cách', $seo->ogTitle());
        $this->assertStringEndsWith('/storage/posts/cover.jpg', (string) $seo->image);
    }

    public function test_a_theme_post_page_follows_the_posts_own_seo_settings(): void
    {
        $this->themeRoute('bai-viet', 'client.posts.show');
        $post = Post::query()->create([
            'title' => ['vi' => 'Cách chọn ống nước'], 'slug' => 'cach-chon-ong', 'content' => ['vi' => '<p>Nội dung bài.</p>'],
            'seo_title' => ['vi' => 'Chọn ống nước đúng cách'], 'is_active' => true, 'published_at' => '2026-10-01 08:00:00',
            'robots_index' => false, 'robots_follow' => true, 'canonical_url' => 'https://spico.vn/blog/chon-ong',
        ]);
        $this->app['env'] = 'production';

        app()->setLocale('vi');
        $seo = app(SeoMetaFactory::class)->forPost($post);
        $article = collect($seo->structuredData)->firstWhere('@type', 'Article');

        $this->assertSame('Chọn ống nước đúng cách', $seo->title);
        // The owner said "do not index" and named the original: both win, even in production.
        $this->assertSame('noindex, follow', $seo->robots());
        $this->assertSame('https://spico.vn/blog/chon-ong', $seo->canonical);
        $this->assertSame('Cách chọn ống nước', $article['headline']);
        $this->assertSame($post->published_at->toAtomString(), $article['datePublished']);
    }

    private function themeRoute(string $path, string $name): void
    {
        // What a theme's routes/theme.php declares for its detail pages.
        Route::middleware(['web', 'setLocale'])->get('{locale}/'.$path.'/{slug}', fn () => 'theme page')->name($name);
        Route::getRoutes()->refreshNameLookups();
    }

    private function page(array $attributes = []): Page
    {
        return Page::query()->create(array_merge([
            'title' => ['vi' => 'Đổi trả', 'en' => 'Returns'],
            'slug' => 'doi-tra',
            'published_html' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true,
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    private function category(string $slug, array $attributes = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => ['vi' => $slug],
            'slug' => $slug,
            'is_active' => true,
            'is_draft' => false,
        ], $attributes));
    }
}
