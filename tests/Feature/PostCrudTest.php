<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->get('/vi/admin/posts')->assertRedirect('/login');
        $this->get('/vi/admin/post-categories')->assertRedirect('/login');
    }

    public function test_regular_users_cannot_access_posts_or_categories(): void
    {
        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer);

        $this->get('/vi/admin/posts')->assertStatus(403);
        $this->get('/vi/admin/post-categories')->assertStatus(403);
    }

    public function test_admin_cannot_access_posts_when_feature_disabled(): void
    {
        // Disable cms_page feature
        FeatureSetting::query()->where('feature_code', 'cms_page')->update(['is_enabled' => false]);

        $this->actingAs($this->adminUser);

        $this->get('/vi/admin/posts')->assertStatus(403);
        $this->get('/vi/admin/post-categories')->assertStatus(403);
    }

    public function test_admin_can_view_post_and_category_listings(): void
    {
        $this->actingAs($this->adminUser);

        $responsePost = $this->get('/vi/admin/posts');
        $responsePost->assertOk();
        $responsePost->assertViewIs('admin.posts.index');
        $responsePost->assertViewHas('posts');

        $responseCategory = $this->get('/vi/admin/post-categories');
        $responseCategory->assertOk();
        $responseCategory->assertViewIs('admin.posts.categories.index');
        $responseCategory->assertViewHas('categories');
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

    public function test_admin_can_update_post_category(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->put('/vi/admin/post-categories/' . $this->category->id, [
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

        $response = $this->delete('/vi/admin/post-categories/' . $this->category->id);
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
            'is_active' => '1',
        ]);

        $response->assertRedirect('/vi/admin/posts');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('posts', [
            'slug' => 'bai-viet-moi-tinh',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_post(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->put('/vi/admin/posts/' . $this->post->id, [
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
    }

    public function test_admin_can_delete_post(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->delete('/vi/admin/posts/' . $this->post->id);
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
        $responseCategory = $this->get('/vi/admin/posts?category_id=' . $category2->id);
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
