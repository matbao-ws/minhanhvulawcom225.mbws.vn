<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Role;
use App\Models\User;
use App\Services\WordPressPostImportService;
use App\Services\WordPressSeoMeta;
use App\Services\WordPressSiteImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Moving a WordPress site here keeps what it ranked with: the SEO title and description Google
 * showed (Rank Math or Yoast), keywords, robots and canonical choices, a 301 from every old
 * address, old image addresses, and links between posts pointing at the new URLs.
 */
class WordPressSeoMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'cms_page'], ['is_enabled' => true]);
        // What a theme's routes/theme.php declares for its post page.
        Route::middleware(['web', 'setLocale'])->get('{locale}/bai-viet/{slug}', fn () => 'theme page')->name('client.posts.show');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_a_wxr_export_brings_rank_math_and_yoast_seo_and_its_old_urls(): void
    {
        $this->fakeImages();
        $path = tempnam(sys_get_temp_dir(), 'wxr');
        file_put_contents($path, $this->wxr());

        $result = app(WordPressPostImportService::class)->import($path);
        @unlink($path);

        $this->assertSame(3, $result['imported']);

        // Rank Math: the title template resolved, keywords as the form's badges.
        $guide = Post::query()->where('slug', 'cach-chon-giay-chay-bo')->sole();
        $this->assertSame('Cách chọn giày chạy bộ - Website cũ', $guide->getTranslation('seo_title', 'vi'));
        $this->assertSame('Hướng dẫn chọn giày chạy bộ cho người mới.', $guide->getTranslation('seo_description', 'vi'));
        $this->assertSame('giày chạy bộ, giày thể thao', $guide->seo_keys);
        $this->assertNull($guide->canonical_url, 'a canonical to the post itself is dropped');
        $this->assertTrue($guide->robots_index);

        $beginner = Post::query()->where('slug', 'giay-cho-nguoi-moi')->sole();
        $this->assertFalse($beginner->robots_index);
        $this->assertFalse($beginner->robots_follow);
        $this->assertSame('https://other.example/goc/', $beginner->canonical_url);

        // Yoast's %%variables%% and its noindex flag.
        $yoast = Post::query()->where('slug', 'bai-yoast')->sole();
        $this->assertSame('Bài Yoast - Website cũ', $yoast->getTranslation('seo_title', 'vi'));
        $this->assertFalse($yoast->robots_index);

        // Links between imported posts go straight to the new URLs; others stay.
        $content = $guide->getTranslation('content', 'vi');
        $this->assertStringContainsString('href="/bai-viet/giay-cho-nguoi-moi"', $content);
        $this->assertStringContainsString('href="https://google.com/"', $content);
        $this->assertSame(1, $result['links_rewritten']);

        // Old addresses: posts, the category, the images.
        $this->get('/cach-chon-giay-chay-bo/')->assertStatus(301)->assertRedirect('/bai-viet/cach-chon-giay-chay-bo');
        $this->get('/2024/01/giay-cho-nguoi-moi/')->assertRedirect('/bai-viet/giay-cho-nguoi-moi');
        $this->get('/category/kien-thuc/')->assertRedirect('/chuyen-muc/kien-thuc');
        $image = $this->get('/wp-content/uploads/2024/01/inline.png');
        $image->assertStatus(301);
        $this->assertStringStartsWith('/storage/posts/wordpress/inline-', parse_url((string) $image->headers->get('Location'), PHP_URL_PATH));
        $this->assertSame(Redirect::ORIGIN_WORDPRESS, Redirect::query()->where('source_path', '/cach-chon-giay-chay-bo')->value('origin'));
        $this->assertGreaterThanOrEqual(4, $result['redirects']);
    }

    public function test_importing_again_keeps_the_redirects_of_posts_it_skips(): void
    {
        $this->fakeImages();
        $path = tempnam(sys_get_temp_dir(), 'wxr');
        file_put_contents($path, $this->wxr());
        app(WordPressPostImportService::class)->import($path);
        Redirect::query()->delete();

        $result = app(WordPressPostImportService::class)->import($path);
        @unlink($path);

        $this->assertSame(3, $result['skipped']);
        $this->get('/cach-chon-giay-chay-bo/')->assertRedirect('/bai-viet/cach-chon-giay-chay-bo');
    }

    public function test_an_mbws_bridge_export_brings_its_posts_and_both_old_addresses(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wxr');
        file_put_contents($path, $this->bridgeWxr());

        $result = app(WordPressPostImportService::class)->import($path);
        @unlink($path);

        // Posts are stored as mbws_post; the product is counted, not imported as a post.
        $this->assertSame(1, $result['imported']);
        $this->assertSame(['mbws_product' => 1], $result['other_types']);

        $post = Post::query()->where('slug', 'what-is-color-masterbatch')->sole();
        $this->assertSame('What is color masterbatch? | Mascom Global', $post->getTranslation('seo_title', 'vi'));
        $this->assertSame('Masterbatch', $post->seo_keys);
        // Rank Math's canonical named an address of the old site: copied, it would point Google at a page the new site does not have.
        $this->assertNull($post->canonical_url);

        // The public address (_mbws_frontend_url) and the old canonical both lead to the post.
        $this->get('/blog/what-is-color-masterbatch')->assertStatus(301)->assertRedirect('/bai-viet/what-is-color-masterbatch');
        $this->get('/what-is-color-masterbatch')->assertStatus(301)->assertRedirect('/bai-viet/what-is-color-masterbatch');
    }

    public function test_a_file_without_posts_says_what_it_holds(): void
    {
        $admin = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Admin', 'permissions' => ['*']])->id]);
        $xml = str_replace('<wp:post_type>mbws_post</wp:post_type>', '<wp:post_type>mbws_product</wp:post_type>', $this->bridgeWxr());
        $file = UploadedFile::fake()->createWithContent('export.xml', $xml);

        $this->actingAs($admin)->from('/vi/admin/posts')
            ->post('/vi/admin/posts/import-wordpress', ['import_file' => $file, 'duplicate_action' => 'skip'])
            ->assertRedirect('/vi/admin/posts')
            ->assertSessionHas('error', 'File WordPress này không có bài viết. File chứa: 2 mbws_product. Sản phẩm WooCommerce nhập ở Sản phẩm → Nhập (file CSV).');
        $this->assertSame(0, Post::query()->count());
    }

    public function test_a_live_site_with_rank_math_headless_brings_its_rendered_seo(): void
    {
        $head = '<title>Bài A thật &#8211; Shop cũ</title><meta name="description" content="Mô tả thật của bài A."/>'
            .'<meta name="robots" content="noindex, follow"/><link rel="canonical" href="https://wp.test/bai-a/"/>';
        Http::fake([
            'wp.test/wp-json/' => Http::response(['name' => 'Shop cũ', 'routes' => ['/wp/v2/posts' => [], '/rankmath/v1/getHead' => []]]),
            'wp.test/wp-json/rankmath/v1/getHead*' => Http::response(['success' => true, 'head' => $head]),
            'wp.test/wp-json/wp/v2/posts*' => Http::response([[
                'id' => 1, 'slug' => 'bai-a', 'status' => 'publish', 'link' => 'https://wp.test/bai-a/',
                'date_gmt' => '2026-09-01T08:00:00', 'title' => ['rendered' => 'Bài A'],
                'content' => ['rendered' => '<p>Xem <a href="https://wp.test/gioi-thieu/">giới thiệu</a>.</p>', 'protected' => false],
                'excerpt' => ['rendered' => ''],
            ]], 200, ['X-WP-TotalPages' => '1']),
            'wp.test/wp-json/wp/v2/pages*' => Http::response([[
                'id' => 2, 'slug' => 'gioi-thieu', 'link' => 'https://wp.test/gioi-thieu/', 'title' => ['rendered' => 'Giới thiệu'],
                'content' => ['rendered' => '<p>Về chúng tôi.</p>', 'protected' => false],
            ]], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $report = app(WordPressSiteImportService::class)->import('https://wp.test', ['posts', 'pages'], 10);

        $post = Post::query()->where('slug', 'bai-a')->sole();
        $this->assertSame('Bài A thật – Shop cũ', $post->getTranslation('seo_title', 'vi'));
        $this->assertSame('Mô tả thật của bài A.', $post->getTranslation('seo_description', 'vi'));
        $this->assertFalse($post->robots_index);
        $this->assertTrue($post->robots_follow);
        $this->assertNull($post->canonical_url);

        $page = Page::query()->where('slug', 'gioi-thieu')->sole();
        $this->assertSame('Bài A thật – Shop cũ', $page->getTranslation('meta_title', 'vi'), 'pages read the same head');
        $this->assertStringContainsString('href="/chinh-sach/gioi-thieu"', $post->fresh()->getTranslation('content', 'vi'));
        $this->assertSame(2, $report['redirects']['redirects']);
        $this->get('/bai-a/')->assertRedirect('/bai-viet/bai-a');
        $this->get('/gioi-thieu/')->assertRedirect('/chinh-sach/gioi-thieu');
    }

    public function test_title_templates_lose_unknown_variables_and_empty_separators(): void
    {
        $meta = app(WordPressSeoMeta::class);

        $this->assertSame('Bài - Site', $meta->resolve('%title% %sep% %page% %sep% %sitename%', ['title' => 'Bài', 'site_name' => 'Site']));
        $this->assertSame('Bài', $meta->resolve('%%title%% %%sep%% %%unknown%%', ['title' => 'Bài']));
        $this->assertSame('Giảm 50% hôm nay', $meta->resolve('Giảm 50% hôm nay', []));
        $this->assertSame('', $meta->resolve('', ['title' => 'x']));
    }

    private function fakeImages(): void
    {
        $png = base64_decode(self::PNG);
        Http::fake(['8.8.8.8/*' => Http::response($png, 200, ['Content-Type' => 'image/png', 'Content-Length' => (string) strlen($png)])]);
    }

    /** The shape of an export from a WordPress fed by the MBWS bridge plugin (a Laravel site's posts). */
    private function bridgeWxr(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <title>Mascom Global</title>
        <link>https://8.8.8.8/cms</link>
        <wp:wxr_version>1.2</wp:wxr_version>
        <wp:base_site_url>https://8.8.8.8/cms</wp:base_site_url>
        <item>
            <title><![CDATA[What is color masterbatch?]]></title>
            <link>https://8.8.8.8/cms/?mbws_post=what-is-color-masterbatch</link>
            <content:encoded><![CDATA[<p>Color masterbatch is a concentrated mixture of pigments.</p>]]></content:encoded>
            <wp:post_id>410</wp:post_id>
            <wp:post_date_gmt><![CDATA[2026-08-20 16:19:54]]></wp:post_date_gmt>
            <wp:post_name><![CDATA[what-is-color-masterbatch]]></wp:post_name>
            <wp:status><![CDATA[publish]]></wp:status>
            <wp:post_type>mbws_post</wp:post_type>
            <wp:postmeta><wp:meta_key><![CDATA[_mbws_frontend_url]]></wp:meta_key><wp:meta_value><![CDATA[https://8.8.8.8/blog/what-is-color-masterbatch]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key><![CDATA[rank_math_focus_keyword]]></wp:meta_key><wp:meta_value><![CDATA[Masterbatch]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key><![CDATA[rank_math_title]]></wp:meta_key><wp:meta_value><![CDATA[What is color masterbatch? | Mascom Global]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key><![CDATA[rank_math_canonical_url]]></wp:meta_key><wp:meta_value><![CDATA[https://8.8.8.8/what-is-color-masterbatch]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key><![CDATA[rank_math_robots]]></wp:meta_key><wp:meta_value><![CDATA[a:1:{i:0;s:5:"index";}]]></wp:meta_value></wp:postmeta>
        </item>
        <item>
            <title><![CDATA[Filler Masterbatch]]></title>
            <link>https://8.8.8.8/san-pham/filler-masterbatch</link>
            <wp:post_id>500</wp:post_id>
            <wp:post_name><![CDATA[filler-masterbatch]]></wp:post_name>
            <wp:status><![CDATA[publish]]></wp:status>
            <wp:post_type>mbws_product</wp:post_type>
        </item>
        <item>
            <title><![CDATA[image]]></title>
            <wp:post_id>501</wp:post_id>
            <wp:post_type>attachment</wp:post_type>
            <wp:attachment_url>https://8.8.8.8/cms/wp-content/uploads/a.jpg</wp:attachment_url>
        </item>
    </channel>
</rss>
XML;
    }

    private function wxr(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <title>Website cũ</title>
        <link>https://8.8.8.8</link>
        <description>Giày chạy bộ chính hãng</description>
        <wp:wxr_version>1.2</wp:wxr_version>
        <wp:base_site_url>https://8.8.8.8</wp:base_site_url>
        <item>
            <title>Cách chọn giày chạy bộ</title>
            <link>https://8.8.8.8/cach-chon-giay-chay-bo/</link>
            <content:encoded><![CDATA[<p>Xem <a href="https://8.8.8.8/2024/01/giay-cho-nguoi-moi/">bài này</a> và <a href="https://google.com/">Google</a>.</p><p><img src="https://8.8.8.8/wp-content/uploads/2024/01/inline.png" alt="Giày"></p>]]></content:encoded>
            <excerpt:encoded><![CDATA[]]></excerpt:encoded>
            <wp:post_id>1</wp:post_id>
            <wp:post_date_gmt>2024-01-05 03:00:00</wp:post_date_gmt>
            <wp:post_name>cach-chon-giay-chay-bo</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
            <category domain="category" nicename="kien-thuc"><![CDATA[Kiến thức]]></category>
            <wp:postmeta><wp:meta_key>rank_math_title</wp:meta_key><wp:meta_value><![CDATA[%title% %sep% %sitename%]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key>rank_math_description</wp:meta_key><wp:meta_value><![CDATA[Hướng dẫn chọn %focuskw% cho người mới.]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key>rank_math_focus_keyword</wp:meta_key><wp:meta_value><![CDATA[giày chạy bộ,giày thể thao, Giày chạy bộ]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key>rank_math_canonical_url</wp:meta_key><wp:meta_value><![CDATA[https://8.8.8.8/cach-chon-giay-chay-bo/]]></wp:meta_value></wp:postmeta>
        </item>
        <item>
            <title>Giày cho người mới</title>
            <link>https://8.8.8.8/2024/01/giay-cho-nguoi-moi/</link>
            <content:encoded><![CDATA[<p>Nội dung.</p>]]></content:encoded>
            <wp:post_id>2</wp:post_id>
            <wp:post_name>giay-cho-nguoi-moi</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
            <wp:postmeta><wp:meta_key>rank_math_robots</wp:meta_key><wp:meta_value><![CDATA[a:2:{i:0;s:7:"noindex";i:1;s:8:"nofollow";}]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key>rank_math_canonical_url</wp:meta_key><wp:meta_value><![CDATA[https://other.example/goc/]]></wp:meta_value></wp:postmeta>
        </item>
        <item>
            <title>Bài Yoast</title>
            <link>https://8.8.8.8/bai-yoast/</link>
            <content:encoded><![CDATA[<p>Yoast.</p>]]></content:encoded>
            <wp:post_id>3</wp:post_id>
            <wp:post_name>bai-yoast</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
            <wp:postmeta><wp:meta_key>_yoast_wpseo_title</wp:meta_key><wp:meta_value><![CDATA[%%title%% %%sep%% %%sitename%%]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key>_yoast_wpseo_meta-robots-noindex</wp:meta_key><wp:meta_value>1</wp:meta_value></wp:postmeta>
        </item>
    </channel>
</rss>
XML;
    }
}
