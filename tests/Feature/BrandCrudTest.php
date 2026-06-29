<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\BrandService;
use App\Services\Catalog\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_services_can_create_update_and_delete_records(): void
    {
        $brandService = app(BrandService::class);

        $brand = $brandService->create([
            'name' => 'Thuong hieu test',
            'description' => 'Mo ta thuong hieu',
            'is_active' => true,
        ]);

        $this->assertSame('Thuong hieu test', $brand->getTranslation('name', 'vi'));
        $this->assertSame('Mo ta thuong hieu', $brand->getTranslation('description', 'vi'));
        $this->assertTrue($brand->is_active);

        $brand = $brandService->update($brand, [
            'name' => 'Thuong hieu updated',
            'description' => 'Mo ta updated',
            'is_active' => false,
        ]);

        $this->assertSame('Thuong hieu updated', $brand->getTranslation('name', 'vi'));
        $this->assertFalse($brand->is_active);

        $brandService->delete($brand);
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_admin_brand_pages_render_for_authenticated_user(): void
    {
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        Brand::query()->create([
            'name' => ['vi' => 'Thuong hieu'],
            'slug' => 'thuong-hieu',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create());

        $this->get('/vi/admin/brands')->assertOk();
        $this->get('/vi/admin/brands/create')->assertOk();
    }

    public function test_admin_can_upload_quick_update_and_sort_brands(): void
    {
        Storage::fake('public');

        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        $first = Brand::query()->create([
            'name' => ['vi' => 'First Brand'],
            'slug' => 'first-brand',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $second = Brand::query()->create([
            'name' => ['vi' => 'Second Brand'],
            'slug' => 'second-brand',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create());

        // Create Brand via request
        $this->post('/vi/admin/brands', [
            'name' => 'Uploaded Brand',
            'slug' => 'uploaded-brand',
            'image_file' => UploadedFile::fake()->image('brand_logo.png'),
            'is_active' => true,
        ])->assertRedirect('/vi/admin/brands');

        $uploaded = Brand::query()->where('slug', 'uploaded-brand')->firstOrFail();
        $this->assertNotNull($uploaded->image_url);

        // Quick update brand via request
        $this->put("/vi/admin/brands/{$uploaded->id}/quick-update", [
            'name' => 'Draft Brand',
            'slug' => 'draft-brand',
            'is_active' => false,
        ])->assertRedirect('/vi/admin/brands');

        $this->assertDatabaseHas('brands', [
            'id' => $uploaded->id,
            'slug' => 'draft-brand',
            'is_active' => false,
        ]);

        // Reorder/sort brands
        $this->postJson('/vi/admin/brands/sort', [
            'ids' => [$second->id, $first->id],
            'start_order' => 0,
        ])->assertOk();

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_product_belongs_to_brand_integration(): void
    {
        $brand = Brand::query()->create([
            'name' => ['vi' => 'Intel'],
            'slug' => 'intel',
            'is_active' => true,
        ]);

        $productService = app(ProductService::class);
        $product = $productService->create([
            'brand_id' => $brand->id,
            'name' => 'Core i9 Processor',
            'sku' => 'I9-12900K',
            'price' => 15000000,
            'stock_quantity' => 10,
            'manage_stock' => true,
            'is_active' => true,
        ]);

        $this->assertEquals($brand->id, $product->brand_id);
        $this->assertEquals('Intel', $product->brand->getTranslation('name', 'vi'));
    }
}
