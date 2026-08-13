<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CategoryService;
use App\Services\Catalog\ProductOptionService;
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
        $optionService = app(ProductOptionService::class);

        $category = $categoryService->create([
            'name' => 'Danh muc test',
            'description' => 'Mo ta',
            'meta_title' => 'SEO danh muc',
            'meta_description' => 'Mo ta SEO danh muc',
            'is_active' => true,
        ]);

        $product = $productService->create([
            'category_id' => $category->id,
            'name' => 'San pham test',
            'sku' => 'TEST-SKU-1',
            'meta_title' => 'SEO san pham',
            'meta_description' => 'Mo ta SEO san pham',
            'price' => 100000,
            'stock_quantity' => 5,
            'manage_stock' => true,
            'is_active' => true,
        ]);

        $optionService->sync($product, [[
            'name' => 'Color',
            'display_type' => 'color',
            'values' => [['label' => 'Red', 'color_hex' => '#ff0000', 'is_active' => true]],
        ], [
            'name' => 'Size',
            'display_type' => 'select',
            'values' => [['label' => 'M', 'is_active' => true]],
        ]]);
        $optionValueIds = $product->fresh()->optionGroups()->with('values')->get()->flatMap->values->pluck('id')->all();

        $variant = $productService->createVariant($product, [
            'name' => 'Do / M',
            'sku' => 'TEST-SKU-1-RED-M',
            'option_value_ids' => $optionValueIds,
            'price' => 110000,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        $this->assertSame('Danh muc test', $category->getTranslation('name', 'vi'));
        $this->assertSame('SEO danh muc', $category->getTranslation('meta_title', 'vi'));
        $this->assertSame('San pham test', $product->getTranslation('name', 'vi'));
        $this->assertSame('SEO san pham', $product->getTranslation('meta_title', 'vi'));
        $this->assertSame(['Red', 'M'], $variant->optionValues()->orderBy('id')->get()->map->label->all());

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
            'meta_title' => ['vi' => 'SEO danh mục cũ'],
            'meta_description' => ['vi' => 'Mô tả SEO danh mục cũ'],
            'is_active' => true,
        ]);

        $brand = \App\Models\Brand::query()->create([
            'name' => ['vi' => 'Intel'],
            'slug' => 'intel',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => ['vi' => 'San pham'],
            'slug' => 'san-pham',
            'sku' => 'SKU-1',
            'image_url' => '/images/products/san-pham.jpg',
            'price' => 100000,
            'short_description' => ['vi' => '<p>Mô tả ngắn</p>'],
            'description' => ['vi' => '<p>Nội dung sản phẩm</p>'],
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create());

        $this->get('/vi/admin/categories')->assertOk();
        $this->get('/vi/admin/categories/create')
            ->assertOk()
            ->assertDontSee('name="meta_title[', false)
            ->assertDontSee('name="meta_description[', false)
            ->assertSee('placeholder="Nhập tên danh mục..."', false)
            ->assertSee('placeholder="Ví dụ: dien-thoai"', false)
            ->assertSee('data-placeholder="Nhập mô tả danh mục..."', false)
            ->assertSee('(bỏ trống để tự tạo từ tên)')
            ->assertSee('<span class="text-danger">*</span>', false);
        $this->get("/vi/admin/categories/{$category->id}/edit")
            ->assertOk()
            ->assertDontSee('name="meta_title[', false)
            ->assertDontSee('name="meta_description[', false)
            ->assertSee('placeholder="Nhập tên danh mục..."', false)
            ->assertSee('placeholder="Ví dụ: dien-thoai"', false)
            ->assertSee('data-placeholder="Nhập mô tả danh mục..."', false)
            ->assertSee('(bỏ trống để tự tạo từ tên)')
            ->assertSee('<span class="text-danger">*</span>', false);
        $this->get('/vi/admin/products')->assertOk();
        $this->get('/vi/admin/products?category_id=' . $category->id)->assertOk();
        $this->get('/vi/admin/products?brand_id=' . $brand->id)->assertOk();
        $this->get('/vi/admin/products?status=1')->assertOk();
        $this->get('/vi/admin/products?status=0')->assertOk();
        $this->get("/vi/admin/products/{$product->id}")
            ->assertOk()
            ->assertViewIs('admin.catalog.products.show')
            ->assertSee('product-show-image', false)
            ->assertSee('object-fit: contain', false)
            ->assertSeeText('Nội dung sản phẩm')
            ->assertSeeText('Intel');

        $this->put("/vi/admin/categories/{$category->id}", [
            'name' => ['vi' => 'Danh mục đã sửa'],
            'slug' => ['vi' => 'danh-muc-da-sua'],
            'is_active' => true,
        ])->assertRedirect('/vi/admin/categories');

        $category->refresh();
        $this->assertSame('SEO danh mục cũ', $category->getTranslation('meta_title', 'vi'));
        $this->assertSame('Mô tả SEO danh mục cũ', $category->getTranslation('meta_description', 'vi'));
    }

    public function test_admin_can_search_and_filter_categories(): void
    {
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        $parent = Category::query()->create([
            'name' => ['vi' => 'Cách nhiệt'],
            'slug' => 'cach-nhiet',
            'is_active' => true,
        ]);

        $child = Category::query()->create([
            'parent_id' => $parent->id,
            'name' => ['vi' => 'Bông thủy tinh'],
            'slug' => 'bong-thuy-tinh',
            'is_active' => true,
        ]);

        $hidden = Category::query()->create([
            'name' => ['vi' => 'Nội thất'],
            'slug' => 'noi-that',
            'is_active' => false,
        ]);

        $this->actingAs(User::factory()->create());

        $listedIds = fn (string $url): array => $this->get($url)
            ->assertOk()
            ->viewData('categories')
            ->pluck('id')
            ->all();

        // Keyword matches name or slug, other categories are excluded
        $this->assertSame([$child->id], $listedIds('/vi/admin/categories?q=thuy-tinh'));
        $this->assertSame([$parent->id], $listedIds('/vi/admin/categories?q=Cách nhiệt'));

        // Status filter
        $this->assertSame([$hidden->id], $listedIds('/vi/admin/categories?status=0'));
        $this->assertNotContains($hidden->id, $listedIds('/vi/admin/categories?status=1'));

        // Parent filter: children of a specific category, and root-only
        $this->assertSame([$child->id], $listedIds('/vi/admin/categories?parent_id='.$parent->id));

        $rootIds = $listedIds('/vi/admin/categories?parent_id=root');
        $this->assertContains($parent->id, $rootIds);
        $this->assertContains($hidden->id, $rootIds);
        $this->assertNotContains($child->id, $rootIds);

        // Combined filters
        $this->assertSame([], $listedIds('/vi/admin/categories?q=thuy-tinh&status=0'));

        // Filtering disables drag sorting; the unfiltered tree keeps it
        $this->get('/vi/admin/categories?q=cach-nhiet')
            ->assertOk()
            ->assertDontSee('ti ti-grip-vertical');

        $unfiltered = $this->get('/vi/admin/categories')->assertOk();
        $unfiltered->assertSee('ti ti-grip-vertical');
        $this->assertContains($child->id, $unfiltered->viewData('categories')->pluck('id')->all());
    }

    public function test_category_visibility_and_draft_are_independent_checkboxes(): void
    {
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        $category = Category::query()->create([
            'name' => ['vi' => 'Cách nhiệt'],
            'slug' => 'cach-nhiet',
            'is_active' => false,
            'is_draft' => true,
        ]);

        $this->actingAs(User::factory()->create());

        // New category defaults to visible and not draft
        $this->get('/vi/admin/categories/create')
            ->assertOk()
            ->assertSee('name="is_active" value="1" id="is_active" checked', false)
            ->assertSee('name="is_draft" value="1" id="is_draft"', false)
            ->assertDontSee('id="is_draft" checked', false)
            ->assertSeeText('Hiển thị')
            ->assertSeeText('Lưu nháp');

        // Hidden + draft category renders the two checkboxes independently
        $this->get("/vi/admin/categories/{$category->id}/edit")
            ->assertOk()
            ->assertDontSee('id="is_active" checked', false)
            ->assertSee('id="is_draft" checked', false);

        // A category can be visible while still flagged as a draft
        $this->put("/vi/admin/categories/{$category->id}", [
            'name' => 'Cách nhiệt',
            'slug' => 'cach-nhiet',
            'is_active' => '1',
            'is_draft' => '1',
        ])->assertRedirect('/vi/admin/categories');
        $category->refresh();
        $this->assertTrue($category->is_active);
        $this->assertTrue($category->is_draft);

        // Unticking both falls back to the hidden "0" inputs
        $this->put("/vi/admin/categories/{$category->id}/quick-update", [
            'name' => 'Cách nhiệt',
            'slug' => 'cach-nhiet',
            'is_active' => '0',
            'is_draft' => '0',
        ])->assertRedirect('/vi/admin/categories');
        $category->refresh();
        $this->assertFalse($category->is_active);
        $this->assertFalse($category->is_draft);

        // Draft badge and draft filter on the listing
        $category->update(['is_draft' => true]);
        $this->get('/vi/admin/categories')
            ->assertOk()
            ->assertSeeText('Nháp');

        $this->assertSame(
            [$category->id],
            $this->get('/vi/admin/categories?draft=1')->assertOk()->viewData('categories')->pluck('id')->all()
        );
        $this->assertNotContains(
            $category->id,
            $this->get('/vi/admin/categories?draft=0')->assertOk()->viewData('categories')->pluck('id')->all()
        );
    }

    public function test_admin_lists_support_page_size_selection(): void
    {
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        foreach (range(1, 20) as $index) {
            Category::query()->create([
                'name' => ['vi' => "Danh mục {$index}"],
                'slug' => "danh-muc-{$index}",
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        $this->actingAs(User::factory()->create());

        // Default page size
        $this->assertSame(15, $this->get('/vi/admin/categories')->assertOk()->viewData('categories')->perPage());

        // Allowed page sizes are honoured, and the summary/selector render
        $this->assertSame(50, $this->get('/vi/admin/categories?per_page=50')->assertOk()->viewData('categories')->perPage());
        $this->get('/vi/admin/categories?per_page=25')
            ->assertOk()
            ->assertSee('name="per_page"', false)
            ->assertSeeText('Số dòng');

        // Values outside the allow-list fall back to the default instead of blowing up the query
        $this->assertSame(15, $this->get('/vi/admin/categories?per_page=5000')->assertOk()->viewData('categories')->perPage());
        $this->assertSame(15, $this->get('/vi/admin/categories?per_page=abc')->assertOk()->viewData('categories')->perPage());

        // Page size survives alongside filters, and filters survive the page-size form
        $filtered = $this->get('/vi/admin/categories?q=danh-muc-1&per_page=25')->assertOk();
        $this->assertSame(25, $filtered->viewData('categories')->perPage());
        $filtered->assertSee('name="q" value="danh-muc-1"', false);

        $this->assertSame(25, $this->get('/vi/admin/brands?per_page=25')->assertOk()->viewData('brands')->perPage());
        $this->assertSame(25, $this->get('/vi/admin/products?per_page=25')->assertOk()->viewData('products')->perPage());
    }

    public function test_empty_catalog_lists_render_the_shared_empty_state(): void
    {
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        $this->actingAs(User::factory()->create());

        // Brands and products start empty; categories only holds the auto-created default
        foreach (['/vi/admin/brands', '/vi/admin/products'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('admin-assets/images/icons/order-empty.png', false)
                ->assertSee('width="240"', false)
                ->assertSeeText('Hiện tại chưa có dữ liệu')
                ->assertDontSee('name="per_page"', false);
        }

        // A search with no hits shows the same empty state instead of a bare table
        $this->get('/vi/admin/categories?q=khong-ton-tai-dau')
            ->assertOk()
            ->assertSee('admin-assets/images/icons/order-empty.png', false)
            ->assertSeeText('Hiện tại chưa có dữ liệu');

        // Non-empty lists keep the table and the pagination controls
        $this->get('/vi/admin/categories')
            ->assertOk()
            ->assertDontSee('admin-assets/images/icons/order-empty.png', false)
            ->assertSee('name="per_page"', false);
    }

    public function test_admin_can_drag_sort_products(): void
    {
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        $first = Product::query()->create([
            'name' => ['vi' => 'San pham 1'],
            'slug' => 'san-pham-1',
            'sku' => 'SORT-1',
            'price' => 1000,
            'is_active' => true,
        ]);
        $second = Product::query()->create([
            'name' => ['vi' => 'San pham 2'],
            'slug' => 'san-pham-2',
            'sku' => 'SORT-2',
            'price' => 2000,
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create());

        // Drag handles show on the plain list and disappear once a filter narrows it
        $this->get('/vi/admin/products')->assertOk()->assertSee('ti ti-grip-vertical', false);
        $this->get('/vi/admin/products?q=san-pham-1')->assertOk()->assertDontSee('ti ti-grip-vertical', false);

        $this->postJson('/vi/admin/products/sort', [
            'ids' => [$second->id, $first->id],
            'start_order' => 0,
        ])->assertOk();

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);

        // The admin listing now follows the manual order
        $this->assertSame(
            [$second->id, $first->id],
            $this->get('/vi/admin/products')->assertOk()->viewData('products')->pluck('id')->all()
        );

        $this->postJson('/vi/admin/products/sort', ['ids' => [999999]])->assertStatus(422);
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
