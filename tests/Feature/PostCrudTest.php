<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Role;
use App\Models\User;
use App\Support\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostCrudTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private User $adminUser;

    private PostCategory $category;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['*'],
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        // Enable the cms_page feature gate by default in setup
        FeatureSetting::query()->updateOrCreate(
            ['feature_code' => 'cms_page'],
            ['is_enabled' => true]
        );

        // Seed some test data
        $this->category = PostCategory::create([
            'name' => ['vi' => 'Tin Tức', 'en' => 'News'],
            'slug' => 'tin-tuc',
            'description' => ['vi' => 'Mô tả tin tức', 'en' => 'News description'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->post = Post::create([
            'category_id' => $this->category->id,
            'title' => ['vi' => 'Bài viết thử nghiệm', 'en' => 'Test blog post'],
            'slug' => 'bai-viet-thu-nghiem',
            'summary' => ['vi' => 'Tóm tắt ngắn', 'en' => 'Short summary'],
            'content' => ['vi' => '<p>Nội dung chi tiết của bài viết thử nghiệm SEO</p>', 'en' => '<p>Detailed content of SEO test post</p>'],
            'is_active' => true,
            'seo_title' => ['vi' => 'Tiêu đề SEO vi', 'en' => 'SEO Title en'],
            'seo_description' => ['vi' => 'Mô tả SEO vi', 'en' => 'SEO Description en'],
            'seo_keys' => 'thử nghiệm',
            'published_at' => now(),
        ]);
    }

    public function test_guests_cannot_access_posts_or_categories(): void
    {
        $this->get('/vi/admin/posts')->assertRedirect('/vi/admin/login');
        $this->post('/vi/admin/posts/import-wordpress')->assertRedirect('/vi/admin/login');
        $this->get('/vi/admin/post-categories')->assertRedirect('/vi/admin/login');
    }

    public function test_regular_users_cannot_access_posts_or_categories(): void
    {
        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer);

        $this->get('/vi/admin/posts')->assertStatus(403);
        $this->post('/vi/admin/posts/import-wordpress')->assertStatus(403);
        $this->get('/vi/admin/post-categories')->assertStatus(403);
    }

    public function test_admin_is_directed_to_support_when_cms_feature_is_disabled(): void
    {
        // Disable cms_page feature
        FeatureSetting::query()->where('feature_code', 'cms_page')->update(['is_enabled' => false]);

        $this->actingAs($this->adminUser);

        $this->get('/vi/admin/posts')
            ->assertRedirect('/vi/admin')
            ->assertSessionHas('error', FeatureGate::SUPPORT_MESSAGE);
        $this->get('/vi/admin/post-categories')
            ->assertRedirect('/vi/admin')
            ->assertSessionHas('error', FeatureGate::SUPPORT_MESSAGE);
    }

    public function test_admin_can_view_post_and_category_listings(): void
    {
        $this->actingAs($this->adminUser);

        $responsePost = $this->get('/vi/admin/posts');
        $responsePost->assertOk();
        $responsePost->assertViewIs('admin.posts.index');
        $responsePost->assertViewHas('posts');
        $responsePost
            ->assertSee('Viết bài mới')
            ->assertSee('/vi/admin/posts/create', false)
            ->assertSee('Import WordPress XML')
            ->assertSee('/vi/admin/posts/import-wordpress', false)
            ->assertSee('accept=".xml,application/xml,text/xml"', false);

        $responseCategory = $this->get('/vi/admin/post-categories');
        $responseCategory->assertOk();
        $responseCategory->assertViewIs('admin.posts.categories.index');
        $responseCategory->assertViewHas('categories');
    }

    public function test_post_list_cells_line_up_with_their_column_headers(): void
    {
        $this->post->update(['seo_score' => 72]);
        $this->actingAs($this->adminUser);

        $html = $this->get('/vi/admin/posts')->assertOk()->getContent();

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);

        $textOf = static function (\DOMNodeList $nodes): array {
            $values = [];
            foreach ($nodes as $node) {
                $values[] = trim(preg_replace('/\s+/u', ' ', $node->textContent));
            }

            return $values;
        };

        $headers = $textOf($xpath->query('//table//thead/tr/th'));
        $cells = $textOf($xpath->query('(//table//tbody/tr)[1]/td'));

        $this->assertSame(count($headers), count($cells));

        $columnOf = function (string $label) use ($headers): int {
            $index = array_search($label, $headers, true);
            $this->assertIsInt($index, "Missing column header [{$label}].");

            return $index;
        };

        $this->assertSame('Tin Tức', $cells[$columnOf('Chuyên mục')]);
        $this->assertSame(
            $this->post->published_at->format('d-m-Y H:i'),
            $cells[$columnOf('Ngày xuất bản')],
        );
        $this->assertSame('72/100', $cells[$columnOf('Điểm SEO')]);
        $this->assertSame(__('admin.posts.fields.active'), $cells[$columnOf('Trạng thái')]);
    }

    public function test_empty_post_list_uses_the_post_empty_image(): void
    {
        $this->post->delete();

        $this->actingAs($this->adminUser)
            ->get('/vi/admin/posts')
            ->assertOk()
            ->assertSee('admin-assets/images/icons/empty-post.png', false)
            ->assertDontSee('solar:document-bold-duotone', false);
    }

    public function test_post_form_contains_live_seo_preview_and_analysis_controls(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/vi/admin/posts/'.$this->post->id.'/edit')
            ->assertOk()
            ->assertSee('seo_google_preview_result', false)
            ->assertSee('rule_internal_links', false)
            ->assertSee('name="canonical_url"', false)
            ->assertSee('name="robots_index"', false)
            ->assertSee('name="robots_follow"', false);
    }

    public function test_create_post_form_contains_placeholders_for_editable_text_fields(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/vi/admin/posts/create')
            ->assertOk()
            ->assertSee('placeholder="Nhập tiêu đề hấp dẫn thu hút người đọc..."', false)
            ->assertSee('placeholder="tieu-de-bai-viet-chuan-seo"', false)
            ->assertSee('placeholder="Nhập tóm tắt thu hút hiển thị ở danh sách bài viết..."', false)
            ->assertSee('data-placeholder="Nhập nội dung chi tiết..."', false)
            ->assertSee('placeholder="Bỏ trống để tự động lấy theo tiêu đề bài viết..."', false)
            ->assertSee('placeholder="Mô tả ngắn gọn nội dung hiển thị trên kết quả tìm kiếm (120-160 ký tự)..."', false)
            ->assertSee('placeholder="Ví dụ: chuẩn seo, gio hang..."', false)
            ->assertSee('placeholder="https://example.com/bai-viet"', false);
    }

    public function test_admin_can_import_wordpress_wxr_posts_categories_and_featured_images(): void
    {
        Storage::fake('public');
        $this->fakeWordPressImages();
        $file = UploadedFile::fake()->createWithContent('wordpress.xml', $this->wordpressWxr());

        $this->actingAs($this->adminUser)
            ->post('/vi/admin/posts/import-wordpress', [
                'import_file' => $file,
                'duplicate_action' => 'skip',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $category = PostCategory::query()->where('slug', 'kien-thuc')->firstOrFail();
        $post = Post::query()->where('slug', 'bai-viet-tu-wordpress')->firstOrFail();

        $this->assertSame($category->id, $post->category_id);
        $this->assertSame('Bài viết từ WordPress', $post->getTranslation('title', 'vi'));
        $content = $post->getTranslation('content', 'vi');
        $this->assertStringContainsString('<h2>Nội dung an toàn</h2>', $content);
        $this->assertStringContainsString('<p>Chi tiết bài viết WordPress.</p>', $content);
        $this->assertStringContainsString('<div class="wp-figure mb-3 text-center">', $content);
        $this->assertStringContainsString('<p class="wp-caption text-muted text-center mt-2">Chú thích ảnh</p>', $content);
        $this->assertStringContainsString('<table class="table table-bordered">', $content);
        $this->assertStringContainsString('<th>Thông số</th>', $content);
        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringStartsWith('/storage/posts/wordpress/featured-', $post->getRawOriginal('image_url'));
        $this->assertStringContainsString('/storage/posts/wordpress/inline-', $post->getTranslation('content', 'vi'));
        $this->assertStringNotContainsString('8.8.8.8/uploads', $post->getTranslation('content', 'vi'));
        $this->assertCount(2, Storage::disk('public')->allFiles('posts/wordpress'));
        $this->assertSame('Tiêu đề SEO WordPress', $post->getTranslation('seo_title', 'vi'));
        $this->assertSame('Mô tả SEO WordPress', $post->getTranslation('seo_description', 'vi'));
        $this->assertSame('wordpress', $post->seo_keys);
        $this->assertTrue($post->is_active);
        $this->assertSame('2026-08-01 03:00:00', $post->published_at?->format('Y-m-d H:i:s'));

        $this->actingAs($this->adminUser)
            ->post('/vi/admin/posts/import-wordpress', [
                'import_file' => UploadedFile::fake()->createWithContent('wordpress.xml', $this->wordpressWxr()),
                'duplicate_action' => 'skip',
            ])
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '1 bỏ qua'));

        $this->assertSame(1, Post::query()->where('slug', 'bai-viet-tu-wordpress')->count());

        $updatedWxr = str_replace('Bài viết từ WordPress', 'Bài WordPress đã cập nhật', $this->wordpressWxr());
        $this->actingAs($this->adminUser)
            ->post('/vi/admin/posts/import-wordpress', [
                'import_file' => UploadedFile::fake()->createWithContent('wordpress.xml', $updatedWxr),
                'duplicate_action' => 'update',
            ])
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '1 cập nhật'));

        $this->assertSame(
            'Bài WordPress đã cập nhật',
            Post::query()->where('slug', 'bai-viet-tu-wordpress')->firstOrFail()->getTranslation('title', 'vi'),
        );
        Http::assertSentCount(2);
    }

    public function test_wordpress_import_rejects_non_xml_files_and_invalid_wxr(): void
    {
        $this->actingAs($this->adminUser)
            ->post('/vi/admin/posts/import-wordpress', [
                'import_file' => UploadedFile::fake()->createWithContent('posts.txt', 'not xml'),
                'duplicate_action' => 'skip',
            ])
            ->assertSessionHasErrors('import_file');

        $this->actingAs($this->adminUser)
            ->post('/vi/admin/posts/import-wordpress', [
                'import_file' => UploadedFile::fake()->createWithContent('posts.xml', '<root/>'),
                'duplicate_action' => 'skip',
            ])
            ->assertSessionHas('error');

        $truncatedWxr = preg_replace('/<\/rss>\s*$/', '', $this->wordpressWxr());
        $this->actingAs($this->adminUser)
            ->post('/vi/admin/posts/import-wordpress', [
                'import_file' => UploadedFile::fake()->createWithContent('truncated.xml', (string) $truncatedWxr),
                'duplicate_action' => 'skip',
            ])
            ->assertSessionHas('error');
    }

    public function test_wordpress_import_ignores_unrelated_libxml_warnings_from_post_processing(): void
    {
        Storage::fake('public');
        $this->fakeWordPressImages();
        libxml_use_internal_errors(true);
        simplexml_load_string('<broken>');
        $this->assertNotEmpty(libxml_get_errors());

        $this->actingAs($this->adminUser)
            ->post('/vi/admin/posts/import-wordpress', [
                'import_file' => UploadedFile::fake()->createWithContent('wordpress.xml', $this->wordpressWxr()),
                'duplicate_action' => 'skip',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('posts', ['slug' => 'bai-viet-tu-wordpress']);
    }

    public function test_admin_can_create_post_category(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/vi/admin/post-categories', [
            'name' => 'Chuyên mục mới',
            'slug' => 'chuyen-muc-moi',
            'description' => 'Mô tả chuyên mục mới',
            'sort_order' => 10,
            'is_active' => '1',
        ]);

        $response->assertRedirect('/vi/admin/post-categories');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('post_categories', [
            'slug' => 'chuyen-muc-moi',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }

    private function wordpressWxr(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <title>Website WordPress</title>
        <wp:wxr_version>1.2</wp:wxr_version>
        <item>
            <title>Ảnh đại diện</title>
            <wp:post_id>99</wp:post_id>
            <wp:post_type>attachment</wp:post_type>
            <wp:status>inherit</wp:status>
            <wp:attachment_url>https://8.8.8.8/uploads/featured.jpg</wp:attachment_url>
        </item>
        <item>
            <title>Bài viết từ WordPress</title>
            <content:encoded><![CDATA[<h2>Nội dung an toàn</h2><script>alert(1)</script>

Chi tiết bài viết WordPress.

<figure><img src="https://8.8.8.8/uploads/inline.png" alt="Ảnh nội dung"><figcaption>Chú thích ảnh</figcaption></figure>

<table><thead><tr><th>Thông số</th></tr></thead><tbody><tr><td>Giá trị</td></tr></tbody></table>]]></content:encoded>
            <excerpt:encoded><![CDATA[Tóm tắt từ WordPress]]></excerpt:encoded>
            <wp:post_id>10</wp:post_id>
            <wp:post_date>2026-08-01 10:00:00</wp:post_date>
            <wp:post_date_gmt>2026-08-01 03:00:00</wp:post_date_gmt>
            <wp:post_name>bai-viet-tu-wordpress</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
            <category domain="category" nicename="kien-thuc"><![CDATA[Kiến thức]]></category>
            <wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>99</wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key>_yoast_wpseo_title</wp:meta_key><wp:meta_value><![CDATA[Tiêu đề SEO WordPress]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key>_yoast_wpseo_metadesc</wp:meta_key><wp:meta_value><![CDATA[Mô tả SEO WordPress]]></wp:meta_value></wp:postmeta>
            <wp:postmeta><wp:meta_key>_yoast_wpseo_focuskw</wp:meta_key><wp:meta_value>wordpress</wp:meta_value></wp:postmeta>
        </item>
    </channel>
</rss>
XML;
    }

    private function fakeWordPressImages(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Http::fake(fn () => Http::response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($png),
        ]));
    }

    public function test_admin_can_update_post_category(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->put('/vi/admin/post-categories/'.$this->category->id, [
            'name' => 'Chuyên mục cập nhật',
            'slug' => 'chuyen-muc-cap-nhat',
            'description' => 'Mô tả chuyên mục cập nhật',
            'sort_order' => 20,
        ]);

        $response->assertRedirect('/vi/admin/post-categories');
        $response->assertSessionHas('success');

        $this->category->refresh();
        $this->assertEquals('chuyen-muc-cap-nhat', $this->category->slug);
        $this->assertEquals(20, $this->category->sort_order);
        $this->assertEquals('Chuyên mục cập nhật', $this->category->getTranslation('name', 'vi'));
    }

    public function test_admin_can_delete_post_category(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->delete('/vi/admin/post-categories/'.$this->category->id);
        $response->assertRedirect('/vi/admin/post-categories');
        $response->assertSessionHas('success');

        $this->assertModelMissing($this->category);
    }

    public function test_admin_can_create_post(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/vi/admin/posts', [
            'title' => 'Bài viết mới tinh',
            'slug' => 'bai-viet-moi-tinh',
            'category_id' => $this->category->id,
            'summary' => 'Tóm tắt bài viết mới',
            'content' => 'Nội dung chi tiết bài viết mới để test SEO tốt nhất.',
            'seo_title' => 'Tiêu đề SEO bài viết mới',
            'seo_description' => 'Mô tả SEO cho bài viết mới để xem có chuẩn hay không',
            'seo_keys' => 'bài viết mới',
            'canonical_url' => 'https://example.com/bai-viet-moi-tinh',
            'robots_index' => '0',
            'robots_follow' => '1',
            'seo_score' => 100,
            'is_active' => '1',
        ]);

        $response->assertRedirect('/vi/admin/posts');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('posts', [
            'slug' => 'bai-viet-moi-tinh',
            'category_id' => $this->category->id,
            'is_active' => true,
            'canonical_url' => 'https://example.com/bai-viet-moi-tinh',
            'robots_index' => false,
            'robots_follow' => true,
        ]);
        $created = Post::query()->where('slug', 'bai-viet-moi-tinh')->firstOrFail();
        $this->assertNotNull($created->seo_score);
        $this->assertNotSame(100, $created->seo_score);
        $this->assertIsArray($created->seo_analysis);
    }

    public function test_admin_can_update_post(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->put('/vi/admin/posts/'.$this->post->id, [
            'title' => 'Bài viết được chỉnh sửa',
            'slug' => 'bai-viet-duoc-chinh-sua',
            'category_id' => $this->category->id,
            'summary' => 'Tóm tắt bài viết mới cập nhật',
            'content' => '<p>Nội dung chi tiết được sửa lại</p>',
            'seo_title' => 'Tiêu đề SEO update',
            'seo_description' => 'Mô tả SEO update',
            'seo_keys' => 'bài viết chỉnh sửa',
        ]);

        $response->assertRedirect('/vi/admin/posts');
        $response->assertSessionHas('success');

        $this->post->refresh();
        $this->assertEquals('bai-viet-duoc-chinh-sua', $this->post->slug);
        $this->assertEquals('Bài viết được chỉnh sửa', $this->post->getTranslation('title', 'vi'));
        $this->assertNotNull($this->post->seo_score);
    }

    public function test_public_post_exposes_canonical_and_robots_metadata(): void
    {
        $this->post->update([
            'canonical_url' => 'https://example.com/bai-viet-thu-nghiem',
            'robots_index' => false,
            'robots_follow' => true,
        ]);

        $this->getJson('/api/public/posts/'.$this->post->slug)
            ->assertOk()
            ->assertJsonPath('data.canonical_url', 'https://example.com/bai-viet-thu-nghiem')
            ->assertJsonPath('data.robots.index', false)
            ->assertJsonPath('data.robots.follow', true)
            ->assertJsonMissingPath('data.seo_score')
            ->assertJsonMissingPath('data.seo_analysis');
    }

    public function test_admin_can_delete_post(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->delete('/vi/admin/posts/'.$this->post->id);
        $response->assertRedirect('/vi/admin/posts');
        $response->assertSessionHas('success');

        $this->assertModelMissing($this->post);
    }

    public function test_admin_can_filter_and_search_posts(): void
    {
        $this->actingAs($this->adminUser);

        // Create another post (inactive, different category)
        $category2 = PostCategory::create([
            'name' => ['vi' => 'Đánh giá', 'en' => 'Reviews'],
            'slug' => 'danh-gia',
            'is_active' => true,
        ]);

        $draftPost = Post::create([
            'category_id' => $category2->id,
            'title' => ['vi' => 'Nháp tin tức', 'en' => 'Draft news'],
            'slug' => 'nhap-tin-tuc',
            'content' => ['vi' => 'Nội dung', 'en' => 'Content'],
            'is_active' => false,
        ]);

        // Search by keyword
        $responseSearch = $this->get('/vi/admin/posts?q=thử nghiệm');
        $postsSearch = $responseSearch->viewData('posts');
        $this->assertTrue($postsSearch->contains($this->post));
        $this->assertFalse($postsSearch->contains($draftPost));

        // Filter by category
        $responseCategory = $this->get('/vi/admin/posts?category_id='.$category2->id);
        $postsCategory = $responseCategory->viewData('posts');
        $this->assertTrue($postsCategory->contains($draftPost));
        $this->assertFalse($postsCategory->contains($this->post));

        // Filter by status (inactive)
        $responseStatus = $this->get('/vi/admin/posts?status=0');
        $postsStatus = $responseStatus->viewData('posts');
        $this->assertTrue($postsStatus->contains($draftPost));
        $this->assertFalse($postsStatus->contains($this->post));
    }
}
