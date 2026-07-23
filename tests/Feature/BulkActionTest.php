<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->create([
            'name' => 'Bulk manager',
            'permissions' => ['*'],
        ]);
        $this->admin = User::factory()->create(['role_id' => $role->id]);

        foreach (['catalog', 'cms_page', 'banner', 'voucher'] as $featureCode) {
            FeatureSetting::query()->create([
                'feature_code' => $featureCode,
                'is_enabled' => true,
            ]);
        }
    }

    public function test_admin_can_bulk_change_status_for_all_supported_modules(): void
    {
        $category = $this->category('category-status');
        $brand = $this->brand('brand-status');
        $product = $this->product($category, $brand, 'product-status');
        $post = $this->makePost('post-status');
        $banner = Banner::query()->create([
            'title' => 'Status banner',
            'image_path' => 'https://example.test/banner.jpg',
            'position' => 'home_main',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $voucher = Voucher::query()->create([
            'code' => 'STATUS',
            'name' => ['vi' => 'Status'],
            'type' => 'fixed',
            'value' => 10000,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);

        $this->from('/vi/admin/products')->patch('/vi/admin/products/bulk', ['ids' => [$product->id], 'action' => 'deactivate'])->assertRedirect('/vi/admin/products');
        $this->from('/vi/admin/categories')->patch('/vi/admin/categories/bulk', ['ids' => [$category->id], 'action' => 'deactivate'])->assertRedirect('/vi/admin/categories');
        $this->from('/vi/admin/brands')->patch('/vi/admin/brands/bulk', ['ids' => [$brand->id], 'action' => 'deactivate'])->assertRedirect('/vi/admin/brands');
        $this->from('/vi/admin/posts')->patch('/vi/admin/posts/bulk', ['ids' => [$post->id], 'action' => 'deactivate'])->assertRedirect('/vi/admin/posts');
        $this->from('/vi/admin/banners')->patch('/vi/admin/banners/bulk', ['ids' => [$banner->id], 'action' => 'deactivate'])->assertRedirect('/vi/admin/banners');
        $this->from('/vi/admin/vouchers')->patch('/vi/admin/vouchers/bulk', ['ids' => [$voucher->id], 'action' => 'deactivate'])->assertRedirect('/vi/admin/vouchers');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'is_active' => false]);
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'is_active' => false, 'published_at' => null]);
        $this->assertDatabaseHas('banners', ['id' => $banner->id, 'is_active' => false]);
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'is_active' => false]);
        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $this->admin->id,
            'action' => 'bulk_status_changed',
        ]);
    }

    public function test_admin_can_bulk_delete_unreferenced_records(): void
    {
        $category = $this->category('category-delete');
        $brand = $this->brand('brand-delete');
        $product = $this->product($category, $brand, 'product-delete');
        $post = $this->makePost('post-delete');
        $banner = Banner::query()->create([
            'title' => 'Delete banner',
            'image_path' => 'https://example.test/delete.jpg',
            'position' => 'home_main',
            'sort_order' => 1,
        ]);
        $voucher = Voucher::query()->create([
            'code' => 'DELETE',
            'name' => ['vi' => 'Delete'],
            'type' => 'fixed',
            'value' => 10000,
        ]);

        $this->actingAs($this->admin);

        $this->from('/vi/admin/products')->patch('/vi/admin/products/bulk', ['ids' => [$product->id], 'action' => 'delete'])->assertRedirect('/vi/admin/products');
        $this->from('/vi/admin/categories')->patch('/vi/admin/categories/bulk', ['ids' => [$category->id], 'action' => 'delete'])->assertRedirect('/vi/admin/categories');
        $this->from('/vi/admin/brands')->patch('/vi/admin/brands/bulk', ['ids' => [$brand->id], 'action' => 'delete'])->assertRedirect('/vi/admin/brands');
        $this->from('/vi/admin/posts')->patch('/vi/admin/posts/bulk', ['ids' => [$post->id], 'action' => 'delete'])->assertRedirect('/vi/admin/posts');
        $this->from('/vi/admin/banners')->patch('/vi/admin/banners/bulk', ['ids' => [$banner->id], 'action' => 'delete'])->assertRedirect('/vi/admin/banners');
        $this->from('/vi/admin/vouchers')->patch('/vi/admin/vouchers/bulk', ['ids' => [$voucher->id], 'action' => 'delete'])->assertRedirect('/vi/admin/vouchers');

        $this->assertModelMissing($product);
        $this->assertModelMissing($category);
        $this->assertModelMissing($brand);
        $this->assertModelMissing($post);
        $this->assertModelMissing($banner);
        $this->assertModelMissing($voucher);
        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $this->admin->id,
            'action' => 'bulk_deleted',
        ]);
    }

    public function test_bulk_delete_blocks_referenced_catalog_data_and_used_vouchers(): void
    {
        $category = $this->category('category-in-use');
        $brand = $this->brand('brand-in-use');
        $this->product($category, $brand, 'product-in-use');
        $voucher = Voucher::query()->create([
            'code' => 'USED',
            'name' => ['vi' => 'Used'],
            'type' => 'fixed',
            'value' => 10000,
            'used_count' => 1,
        ]);

        $this->actingAs($this->admin);

        $this->from('/vi/admin/categories')
            ->patch('/vi/admin/categories/bulk', ['ids' => [$category->id], 'action' => 'delete'])
            ->assertRedirect('/vi/admin/categories')
            ->assertSessionHas('error');
        $this->from('/vi/admin/brands')
            ->patch('/vi/admin/brands/bulk', ['ids' => [$brand->id], 'action' => 'delete'])
            ->assertRedirect('/vi/admin/brands')
            ->assertSessionHas('error');
        $this->from('/vi/admin/vouchers')
            ->patch('/vi/admin/vouchers/bulk', ['ids' => [$voucher->id], 'action' => 'delete'])
            ->assertRedirect('/vi/admin/vouchers')
            ->assertSessionHas('error');

        $this->assertModelExists($category);
        $this->assertModelExists($brand);
        $this->assertModelExists($voucher);
    }

    public function test_bulk_actions_require_a_valid_selection_and_render_controls(): void
    {
        $this->actingAs($this->admin);

        $this->patch('/vi/admin/products/bulk', ['action' => 'delete'])
            ->assertSessionHasErrors('ids');

        $this->get('/vi/admin/products')->assertOk()->assertSee('bulk-products-form');
        $this->get('/vi/admin/categories')->assertOk()->assertSee('bulk-categories-form');
        $this->get('/vi/admin/brands')->assertOk()->assertSee('bulk-brands-form');
        $this->get('/vi/admin/posts')->assertOk()->assertSee('bulk-posts-form');
        $this->get('/vi/admin/banners')->assertOk()->assertSee('bulk-banners-form');
        $this->get('/vi/admin/vouchers')->assertOk()->assertSee('bulk-vouchers-form');
    }

    private function category(string $slug): Category
    {
        return Category::query()->create([
            'name' => ['vi' => $slug],
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function brand(string $slug): Brand
    {
        return Brand::query()->create([
            'name' => ['vi' => $slug],
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function product(Category $category, Brand $brand, string $slug): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => ['vi' => $slug],
            'slug' => $slug,
            'price' => 100000,
            'is_active' => true,
        ]);
    }

    private function makePost(string $slug): Post
    {
        $postCategory = PostCategory::query()->firstOrCreate(
            ['slug' => 'bulk-post-category'],
            ['name' => ['vi' => 'Bulk'], 'is_active' => true],
        );

        return Post::query()->create([
            'category_id' => $postCategory->id,
            'title' => ['vi' => $slug],
            'slug' => $slug,
            'content' => ['vi' => '<p>Bulk content</p>'],
            'is_active' => true,
            'published_at' => now(),
        ]);
    }
}
