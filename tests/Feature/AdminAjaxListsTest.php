<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lists that update in place (public/admin-assets/js/admin-ajax.js): the page marks the part that
 * changes, the server answers a fetched request exactly as a normal one (same route, permission,
 * validation, redirect back with a flash), and the flash is also there as data for the script.
 */
class AdminAjaxListsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cms_page', 'catalog', 'vouchers', 'banners'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        $this->admin = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Root', 'permissions' => ['*'], 'is_superadmin' => true, 'is_system' => true])->id]);
    }

    public function test_list_pages_mark_the_part_that_updates_in_place(): void
    {
        $pages = [
            '/vi/admin/posts' => 'posts',
            '/vi/admin/products' => 'products',
            '/vi/admin/categories' => 'categories',
            '/vi/admin/brands' => 'brands',
            '/vi/admin/orders' => 'orders',
            '/vi/admin/customers' => 'customers',
            '/vi/admin/users' => 'users',
            '/vi/admin/roles' => 'roles',
            '/vi/admin/activity-logs' => 'activity',
        ];

        foreach ($pages as $url => $list) {
            $response = $this->actingAs($this->admin)->get($url);
            $this->assertSame(200, $response->status(), $url.' → '.$response->headers->get('Location'));
            $response
                ->assertSee('data-ajax-list="'.$list.'"', false)
                ->assertSee('admin-assets/js/admin-ajax.js', false);
        }
    }

    public function test_the_flash_is_also_data_for_a_fetched_answer(): void
    {
        $post = Post::query()->create(['title' => ['vi' => 'Bài'], 'slug' => 'bai', 'content' => ['vi' => '<p>x</p>'], 'is_active' => true]);

        $this->actingAs($this->admin)->from('/vi/admin/posts?q=bai')
            ->withHeaders(['X-Admin-Ajax' => '1', 'Accept' => 'text/html'])
            ->patch('/vi/admin/posts/bulk', ['ids' => [$post->id], 'action' => 'deactivate'])
            // The same answer as a normal submit: back to the list it came from, filters kept.
            ->assertRedirect('/vi/admin/posts?q=bai');

        $html = $this->actingAs($this->admin)->get('/vi/admin/posts?q=bai')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<script type="application\/json" data-admin-flash>\{"toast":\{"icon":"success"/', $html);
        $this->assertFalse($post->fresh()->is_active);
    }

    public function test_a_row_switch_shows_and_hides_through_the_bulk_endpoint(): void
    {
        $post = Post::query()->create(['title' => ['vi' => 'Bài'], 'slug' => 'bai', 'content' => ['vi' => '<p>x</p>'], 'is_active' => true]);

        $this->actingAs($this->admin)->get('/vi/admin/posts')->assertOk()
            ->assertSee('data-status-switch', false)
            ->assertSee('name="action" value="deactivate"', false);

        // Without posts.update only the badge: nothing to click.
        $viewer = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Viewer', 'permissions' => ['posts.view']])->id]);
        $this->actingAs($viewer)->get('/vi/admin/posts')->assertOk()->assertDontSee('data-status-switch', false);
        $this->actingAs($viewer)->patch('/vi/admin/posts/bulk', ['ids' => [$post->id], 'action' => 'deactivate'])->assertForbidden();
        $this->assertTrue($post->fresh()->is_active);
    }

    public function test_price_and_stock_are_edited_from_the_product_list(): void
    {
        $product = Product::query()->create(['name' => ['vi' => 'Ống nhựa'], 'slug' => 'ong-nhua', 'sku' => 'ON-1', 'price' => 1000, 'stock_quantity' => 5, 'is_active' => true]);

        $this->actingAs($this->admin)->get('/vi/admin/products')->assertOk()
            ->assertSee('id="quick-product-'.$product->id.'"', false)
            ->assertSee('/vi/admin/products/'.$product->id.'/quick-update', false);

        $this->actingAs($this->admin)->from('/vi/admin/products')
            ->patch('/vi/admin/products/'.$product->id.'/quick-update', ['price' => '125000', 'stock_quantity' => '12'])
            ->assertRedirect('/vi/admin/products')
            ->assertSessionHas('success', __('catalog.products.quick_updated'));

        $product->refresh();
        $this->assertSame(125000.0, (float) $product->price);
        $this->assertSame(12, $product->stock_quantity);
        $log = AdminActivityLog::query()->where('subject_id', $product->id)->latest('id')->firstOrFail();
        $this->assertSame([1000.0, 125000.0], array_map('floatval', $log->changes['price']));
        $this->assertSame([5, 12], array_map('intval', $log->changes['stock_quantity']));
    }

    public function test_quick_edit_refuses_bad_values_variant_products_and_viewers(): void
    {
        $product = Product::query()->create(['name' => ['vi' => 'Ống nhựa'], 'slug' => 'ong-nhua', 'sku' => 'ON-1', 'price' => 1000, 'stock_quantity' => 5, 'is_active' => true]);

        $this->actingAs($this->admin)->from('/vi/admin/products')
            ->patch('/vi/admin/products/'.$product->id.'/quick-update', ['price' => '-1', 'stock_quantity' => '2.5'])
            ->assertSessionHasErrors(['price', 'stock_quantity']);

        $viewer = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Viewer', 'permissions' => ['products.view']])->id]);
        $this->actingAs($viewer)->patch('/vi/admin/products/'.$product->id.'/quick-update', ['price' => '1', 'stock_quantity' => '1'])->assertForbidden();
        $this->actingAs($viewer)->get('/vi/admin/products')->assertOk()->assertDontSee('id="quick-product-', false);

        // Variants carry their own price and stock: edited there, not from the list.
        ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'ON-1-DO', 'price' => 1000, 'stock_quantity' => 1, 'is_active' => true]);
        $this->actingAs($this->admin)->from('/vi/admin/products')
            ->patch('/vi/admin/products/'.$product->id.'/quick-update', ['price' => '9', 'stock_quantity' => '9'])
            ->assertSessionHas('error', __('catalog.products.quick_edit_variants'));

        $product->refresh();
        $this->assertSame([1000.0, 5], [(float) $product->price, $product->stock_quantity]);
    }
}
