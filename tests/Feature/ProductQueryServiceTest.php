<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Services\Catalog\ProductQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public catalog read contract. This lives in a service precisely so both
 * the JSON API and a same-origin Blade storefront share one implementation —
 * these assertions are what stops the two from drifting.
 */
class ProductQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductQueryService $service;

    private Category $phones;

    private Brand $apple;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ProductQueryService::class);

        $this->phones = Category::query()->create([
            'name' => ['vi' => 'Điện thoại', 'en' => 'Phones'],
            'slug' => 'dien-thoai',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $laptops = Category::query()->create([
            'name' => ['vi' => 'Laptop', 'en' => 'Laptops'],
            'slug' => 'laptop',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $this->apple = Brand::query()->create(['name' => 'Apple', 'slug' => 'apple', 'is_active' => true, 'sort_order' => 1]);
        $samsung = Brand::query()->create(['name' => 'Samsung', 'slug' => 'samsung', 'is_active' => true, 'sort_order' => 2]);

        $this->makeProduct('iPhone 15 Pro', 'iphone-15-pro', 'SKU-IP15', 30_000_000, $this->phones->id, $this->apple->id);
        $this->makeProduct('Galaxy S24', 'galaxy-s24', 'SKU-GS24', 20_000_000, $this->phones->id, $samsung->id);
        $this->makeProduct('MacBook Air', 'macbook-air', 'SKU-MBA', 40_000_000, $laptops->id, $this->apple->id);
        $this->makeProduct('Hidden Phone', 'hidden-phone', 'SKU-HID', 1_000_000, $this->phones->id, $this->apple->id, false);
    }

    private function makeProduct(
        string $name,
        string $slug,
        string $sku,
        float $price,
        int $categoryId,
        int $brandId,
        bool $active = true,
    ): Product {
        return Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => ['vi' => $name, 'en' => $name],
            'description' => ['vi' => "Mô tả {$name}", 'en' => "About {$name}"],
            'slug' => $slug,
            'sku' => $sku,
            'price' => $price,
            'stock_quantity' => 10,
            'manage_stock' => true,
            'is_active' => $active,
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function slugs(array $filters = []): array
    {
        return $this->service->listing($filters)->pluck('slug')->all();
    }

    public function test_only_active_products_are_listed(): void
    {
        $slugs = $this->slugs();

        $this->assertContains('iphone-15-pro', $slugs);
        $this->assertNotContains('hidden-phone', $slugs, 'An inactive product must never reach the storefront.');
    }

    public function test_filters_by_localized_category_slug(): void
    {
        $this->assertEqualsCanonicalizing(
            ['iphone-15-pro', 'galaxy-s24'],
            $this->slugs(['category' => 'dien-thoai']),
        );
    }

    public function test_filters_by_localized_brand_slug(): void
    {
        $this->assertEqualsCanonicalizing(
            ['iphone-15-pro', 'macbook-air'],
            $this->slugs(['brand' => 'apple']),
        );
    }

    public function test_an_unknown_slug_yields_nothing_rather_than_everything(): void
    {
        // The dangerous failure mode: silently ignoring the filter and
        // returning the whole catalogue.
        $this->assertSame([], $this->slugs(['category' => 'does-not-exist']));
        $this->assertSame([], $this->slugs(['brand' => 'does-not-exist']));
    }

    public function test_keyword_matches_name_description_and_sku(): void
    {
        $this->assertSame(['galaxy-s24'], $this->slugs(['q' => 'Galaxy']));
        $this->assertSame(['macbook-air'], $this->slugs(['q' => 'Mô tả MacBook']));
        $this->assertSame(['iphone-15-pro'], $this->slugs(['q' => 'SKU-IP15']));
        $this->assertSame([], $this->slugs(['q' => 'nothing-matches-this']));
    }

    public function test_keyword_search_is_scoped_to_the_active_locale(): void
    {
        // Descriptions are translatable; searching must look at the requested
        // locale's text only, not every translation.
        app()->setLocale('vi');
        $this->assertSame([], $this->slugs(['q' => 'About MacBook']));

        app()->setLocale('en');
        $this->assertSame(['macbook-air'], $this->slugs(['q' => 'About MacBook']));
    }

    public function test_filters_by_price_range(): void
    {
        $this->assertEqualsCanonicalizing(['iphone-15-pro', 'macbook-air'], $this->slugs(['min_price' => 25_000_000]));
        $this->assertEqualsCanonicalizing(['galaxy-s24'], $this->slugs(['max_price' => 25_000_000]));
        $this->assertSame(['iphone-15-pro'], $this->slugs(['min_price' => 25_000_000, 'max_price' => 35_000_000]));
    }

    public function test_sorts_by_price_in_both_directions(): void
    {
        $this->assertSame(
            ['galaxy-s24', 'iphone-15-pro', 'macbook-air'],
            $this->slugs(['sort_by' => 'price_asc']),
        );
        $this->assertSame(
            ['macbook-air', 'iphone-15-pro', 'galaxy-s24'],
            $this->slugs(['sort_by' => 'price_desc']),
        );
    }

    public function test_an_unknown_sort_falls_back_to_latest_instead_of_erroring(): void
    {
        $this->assertCount(3, $this->slugs(['sort_by' => 'price; DROP TABLE products']));
        $this->assertCount(3, $this->slugs(['sort_by' => null]));
    }

    public function test_blank_filters_are_ignored(): void
    {
        // Query strings routinely arrive as "?q=&category=" — those must not
        // be treated as a filter for the empty string.
        $this->assertCount(3, $this->slugs(['q' => '', 'category' => '', 'brand' => null, 'min_price' => '', 'max_price' => '']));
    }

    public function test_filters_combine(): void
    {
        $this->assertSame(['iphone-15-pro'], $this->slugs(['category' => 'dien-thoai', 'brand' => 'apple']));
    }

    public function test_detail_returns_null_for_missing_or_inactive_products(): void
    {
        $this->assertNull($this->service->findActiveDetail('does-not-exist'));
        $this->assertNull($this->service->findActiveDetail('hidden-phone'));
    }

    public function test_detail_eager_loads_what_a_product_page_renders(): void
    {
        $product = $this->service->findActiveDetail('iphone-15-pro');

        $this->assertNotNull($product);
        foreach (['localizedSlugs', 'category', 'brand', 'optionGroups', 'variants'] as $relation) {
            $this->assertTrue($product->relationLoaded($relation), "{$relation} must be eager loaded.");
        }
    }

    public function test_reviews_are_only_loaded_when_the_review_feature_is_enabled(): void
    {
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'review'], ['is_enabled' => false]);
        $this->assertFalse(
            app(ProductQueryService::class)->findActiveDetail('iphone-15-pro')->relationLoaded('reviews'),
        );

        FeatureSetting::query()->updateOrCreate(['feature_code' => 'review'], ['is_enabled' => true]);
        $this->assertTrue(
            app(ProductQueryService::class)->findActiveDetail('iphone-15-pro')->relationLoaded('reviews'),
        );
    }
}
