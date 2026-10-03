<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** wp:import copies a live WordPress site's public content, keeping slugs, for test sites. */
class ImportWordPressSiteTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        Http::fake([
            'wp.test/wp-json/' => Http::response(['name' => 'Bệnh viện', 'routes' => ['/wp/v2/posts' => []]]),
            'wp.test/wp-json/wp/v2/posts*' => fn (Request $request) => str_contains($request->url(), 'page=1')
                ? Http::response([$this->wpPost(11, 'gioi-thieu-dich-vu', 'Giới thiệu &#8211; dịch vụ'), $this->wpPost(12, 'lich-kham', 'Lịch khám')], 200, ['X-WP-TotalPages' => '1'])
                : Http::response(['code' => 'rest_post_invalid_page_number'], 400),
            'wp.test/wp-json/wp/v2/pages*' => Http::response([[
                'id' => 21, 'slug' => 'lien-he', 'title' => ['rendered' => 'Liên hệ'],
                'content' => ['rendered' => '<p>Số 1 Tiên Yên.</p><img src="https://8.8.8.8/wp-content/uploads/map.png">', 'protected' => false],
                'yoast_head_json' => ['title' => 'Liên hệ bệnh viện', 'description' => 'Địa chỉ và điện thoại.'],
            ]], 200, ['X-WP-TotalPages' => '1']),
            'wp.test/wp-json/wc/store/v1/products*' => Http::response([[
                'id' => 31, 'slug' => 'ong-nhua-upvc', 'name' => 'Ống nhựa uPVC', 'sku' => 'UPVC-21',
                'description' => '<p>Ống bền.</p>', 'short_description' => '<p>Ống uPVC.</p>',
                'prices' => ['price' => '2500000', 'regular_price' => '3000000', 'sale_price' => '2500000', 'currency_minor_unit' => 2],
                'images' => [['src' => 'https://8.8.8.8/wp-content/uploads/ong.png']],
                'categories' => [['id' => 5, 'name' => 'Ống &amp; phụ kiện', 'slug' => 'ong-phu-kien']],
            ]], 200, ['X-WP-TotalPages' => '1']),
            '8.8.8.8/wp-content/uploads/*' => fn () => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/png', 'Content-Length' => (string) strlen(base64_decode(self::PNG))]),
        ]);
    }

    public function test_posts_pages_and_products_are_copied_with_their_slugs(): void
    {
        $this->artisan('wp:import', ['url' => 'https://wp.test'])->expectsOutputToContain('posts: 2 imported')->assertSuccessful();

        $post = Post::query()->where('slug', 'gioi-thieu-dich-vu')->sole();
        $this->assertSame('Giới thiệu – dịch vụ', $post->getTranslation('title', 'vi'));
        $this->assertSame('Tin tức', $post->category->getTranslation('name', 'vi'));
        $this->assertSame('SEO giới thiệu', $post->getTranslation('seo_title', 'vi'));
        // Images land in this site's library, not on the WordPress host.
        $this->assertStringNotContainsString('8.8.8.8', (string) $post->getRawOriginal('image_url'));
        $this->assertStringNotContainsString('8.8.8.8', (string) $post->getTranslation('content', 'vi'));
        $this->assertSame('gioi-thieu-dich-vu', $post->canonicalSlug('vi'));

        $page = Page::query()->where('slug', 'lien-he')->sole();
        $this->assertSame('Liên hệ bệnh viện', $page->getTranslation('meta_title', 'vi'));
        $this->assertTrue($page->is_active);

        $product = Product::query()->where('slug', 'ong-nhua-upvc')->sole();
        $this->assertSame(['25000.00', '30000.00', 'UPVC-21'], [(string) $product->price, (string) $product->compare_at_price, $product->sku]);
        $this->assertSame('Ống & phụ kiện', Category::query()->find($product->category_id)->getTranslation('name', 'vi'));

        // Running again skips what exists; --update refreshes it.
        $this->artisan('wp:import', ['url' => 'https://wp.test', '--types' => 'posts'])->expectsOutputToContain('posts: 0 imported, 0 updated, 2 skipped')->assertSuccessful();
        $this->artisan('wp:import', ['url' => 'https://wp.test', '--types' => 'posts', '--update' => true])->expectsOutputToContain('posts: 0 imported, 2 updated')->assertSuccessful();
        $this->assertSame(2, Post::query()->count());
    }

    public function test_a_site_without_a_rest_api_is_reported_and_production_is_refused(): void
    {
        $this->artisan('wp:import', ['url' => 'https://no-api.test'])->expectsOutputToContain('REST API')->assertFailed();

        $this->app['env'] = 'production';
        $this->artisan('wp:import', ['url' => 'https://wp.test'])->expectsOutputToContain('staging or local')->assertFailed();
        $this->assertSame(0, Post::query()->count());
    }

    private function wpPost(int $id, string $slug, string $title): array
    {
        return [
            'id' => $id, 'slug' => $slug, 'status' => 'publish', 'date_gmt' => '2026-09-01T08:00:00',
            'title' => ['rendered' => $title],
            'content' => ['rendered' => '<p>Nội dung '.$id.'</p><img src="https://8.8.8.8/wp-content/uploads/a'.$id.'.png">', 'protected' => false],
            'excerpt' => ['rendered' => '<p>Tóm tắt</p>'],
            'featured_media' => 100 + $id,
            'yoast_head_json' => ['title' => 'SEO giới thiệu', 'description' => 'Mô tả SEO'],
            '_embedded' => [
                'wp:featuredmedia' => [['source_url' => 'https://8.8.8.8/wp-content/uploads/f'.$id.'.png']],
                'wp:term' => [[['taxonomy' => 'category', 'name' => 'Tin tức', 'slug' => 'tin-tuc']], [['taxonomy' => 'post_tag', 'name' => 'x', 'slug' => 'x']]],
            ],
        ];
    }
}
