<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CategoryService;
use App\Services\Catalog\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_services_can_create_update_and_delete_records(): void
    {
        $categoryService = app(CategoryService::class);
        $productService = app(ProductService::class);

        $category = $categoryService->create([
            'name' => 'Danh muc test',
            'description' => 'Mo ta',
            'is_active' => true,
        ]);

        $product = $productService->create([
            'category_id' => $category->id,
            'name' => 'San pham test',
            'sku' => 'TEST-SKU-1',
            'price' => 100000,
            'stock_quantity' => 5,
            'manage_stock' => true,
            'is_active' => true,
        ]);

        $variant = $productService->createVariant($product, [
            'name' => 'Do / M',
            'sku' => 'TEST-SKU-1-RED-M',
            'option_names' => ['Color', 'Size'],
            'option_values' => ['Red', 'M'],
            'price' => 110000,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        $this->assertSame('Danh muc test', $category->getTranslation('name', 'vi'));
        $this->assertSame('San pham test', $product->getTranslation('name', 'vi'));
        $this->assertSame(['Color' => 'Red', 'Size' => 'M'], $variant->option_values);

        $productService->deleteVariant($variant);
        $productService->delete($product);
        $categoryService->delete($category);

        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_admin_catalog_pages_render_for_authenticated_user(): void
    {
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        $category = Category::query()->create([
            'name' => ['vi' => 'Danh muc'],
            'slug' => 'danh-muc',
            'is_active' => true,
        ]);

        $brand = \App\Models\Brand::query()->create([
            'name' => ['vi' => 'Intel'],
            'slug' => 'intel',
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => ['vi' => 'San pham'],
            'slug' => 'san-pham',
            'sku' => 'SKU-1',
            'price' => 100000,
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create());

        $this->get('/vi/admin/categories')->assertOk();
        $this->get('/vi/admin/products')->assertOk();
        $this->get('/vi/admin/products?category_id=' . $category->id)->assertOk();
        $this->get('/vi/admin/products?brand_id=' . $brand->id)->assertOk();
        $this->get('/vi/admin/products?status=1')->assertOk();
        $this->get('/vi/admin/products?status=0')->assertOk();
    }

    public function test_admin_can_upload_quick_update_and_sort_categories(): void
    {
        Storage::fake('public');

        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        $first = Category::query()->create([
            'name' => ['vi' => 'First'],
            'slug' => 'first',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $second = Category::query()->create([
            'name' => ['vi' => 'Second'],
            'slug' => 'second',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create());

        $this->post('/vi/admin/categories', [
            'name' => 'Uploaded category',
            'slug' => 'uploaded-category',
            'image_file' => UploadedFile::fake()->image('category.jpg'),
            'is_active' => true,
        ])->assertRedirect('/vi/admin/categories');

        $uploaded = Category::query()->where('slug', 'uploaded-category')->firstOrFail();
        $this->assertNotNull($uploaded->image_url);

        $this->put("/vi/admin/categories/{$uploaded->id}/quick-update", [
            'name' => 'Draft category',
            'slug' => 'draft-category',
            'is_active' => false,
        ])->assertRedirect('/vi/admin/categories');

        $this->assertDatabaseHas('categories', [
            'id' => $uploaded->id,
            'slug' => 'draft-category',
            'is_active' => false,
        ]);

        $this->postJson('/vi/admin/categories/sort', [
            'ids' => [$second->id, $first->id],
            'start_order' => 0,
        ])->assertOk();

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }
}
