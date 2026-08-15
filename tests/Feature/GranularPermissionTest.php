<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The point of splitting `manage_*` into view/create/update/delete: a role can
 * now be read-only.
 */
class GranularPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        (new \Database\Seeders\PermissionSeeder())->run();

        foreach (['catalog', 'cms_page'] as $feature) {
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

    private function product(): Product
    {
        return Product::query()->create([
            'name' => ['vi' => 'iPhone'],
            'slug' => 'iphone',
            'sku' => 'IP-1',
            'price' => 1000,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);
    }

    public function test_view_only_role_can_browse_products_but_not_change_them(): void
    {
        $this->actingWith(['products.view']);
        $product = $this->product();

        $this->get('/vi/admin/products')->assertOk();
        $this->get('/vi/admin/products/'.$product->id)->assertOk();

        $this->get('/vi/admin/products/create')->assertForbidden();
        $this->get('/vi/admin/products/'.$product->id.'/edit')->assertForbidden();
        $this->delete('/vi/admin/products/'.$product->id)->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_editor_role_can_update_but_not_delete(): void
    {
        $this->actingWith(['products.view', 'products.update']);
        $product = $this->product();

        $this->get('/vi/admin/products/'.$product->id.'/edit')->assertOk();
        $this->get('/vi/admin/products/create')->assertForbidden();
        $this->delete('/vi/admin/products/'.$product->id)->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /**
     * Bulk delete routes through the update endpoint, so it has to re-check the
     * delete permission or `update` would quietly include destruction.
     */
    public function test_bulk_delete_requires_the_delete_permission(): void
    {
        $this->actingWith(['products.view', 'products.update']);
        $product = $this->product();

        $this->patch('/vi/admin/products/bulk', [
            'ids' => [$product->id],
            'action' => 'delete',
        ])->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);

        // The same endpoint still performs non-destructive bulk edits.
        $this->from('/vi/admin/products')->patch('/vi/admin/products/bulk', [
            'ids' => [$product->id],
            'action' => 'deactivate',
        ])->assertRedirect('/vi/admin/products');

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_bulk_delete_succeeds_with_the_delete_permission(): void
    {
        $this->actingWith(['products.view', 'products.update', 'products.delete']);
        $product = $this->product();

        $this->from('/vi/admin/products')->patch('/vi/admin/products/bulk', [
            'ids' => [$product->id],
            'action' => 'delete',
        ])->assertRedirect('/vi/admin/products');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_creator_role_can_reach_the_create_form(): void
    {
        $this->actingWith(['posts.view', 'posts.create']);

        $this->get('/vi/admin/posts/create')->assertOk();
        $this->get('/vi/admin/posts')->assertOk();
    }

    public function test_post_deleter_cannot_edit(): void
    {
        $this->actingWith(['posts.view', 'posts.delete']);
        $post = Post::query()->create([
            'title' => ['vi' => 'Bài viết'],
            'slug' => 'bai-viet',
            'content' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true,
        ]);

        $this->get('/vi/admin/posts/'.$post->id.'/edit')->assertForbidden();
        $this->from('/vi/admin/posts')->delete('/vi/admin/posts/'.$post->id)->assertRedirect();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_sidebar_hides_modules_the_role_cannot_view(): void
    {
        $this->actingWith(['posts.view']);

        $response = $this->get('/vi/admin')->assertOk();

        $response->assertSee('/vi/admin/posts', false);
        $response->assertDontSee('/vi/admin/products', false);
        $response->assertDontSee('/vi/admin/roles', false);
    }
}
