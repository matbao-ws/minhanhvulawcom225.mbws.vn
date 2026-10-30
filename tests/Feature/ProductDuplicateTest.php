<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Packages\PackageSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->updateOrCreate(['feature_code' => 'catalog'], ['is_enabled' => true]);
    }

    public function test_the_copy_has_everything_but_starts_hidden_and_out_of_stock(): void
    {
        $original = $this->productWithVariants();

        $this->actingAs($this->admin(['products.view', 'products.create']))
            ->post("/vi/admin/products/{$original->id}/duplicate")
            ->assertRedirect()->assertSessionHas('success');

        $copy = Product::query()->whereKeyNot($original->id)->sole();
        $this->assertSame(['vi' => 'Áo thun (bản sao)', 'en' => 'T-shirt (copy)'], $copy->getTranslations('name'));
        $this->assertSame($original->getTranslations('description'), $copy->getTranslations('description'));
        $this->assertSame($original->getTranslations('meta_title'), $copy->getTranslations('meta_title'));
        $this->assertSame([$original->category_id, '250000.00', 'AO-1-COPY'], [$copy->category_id, $copy->price, $copy->sku]);
        $this->assertNotSame($original->slug, $copy->slug);
        $this->assertSame([false, false, 0, null], [$copy->is_active, $copy->is_featured, $copy->stock_quantity, $copy->canonical_url]);
        // Media is copied as stored: the same relative paths, never this host's absolute URL.
        $images = fn (Product $product) => DB::table('product_images')->where('product_id', $product->id)->orderBy('sort_order')->pluck('image_url')->all();
        $this->assertSame(['/products/a.jpg', '/products/b.jpg'], $images($copy));
        $this->assertSame($images($original), $images($copy));
        $this->assertSame('/products/main.jpg', DB::table('products')->where('id', $copy->id)->value('image_url'));

        // Options and variants are new rows; each variant points at the copy's own option values.
        $group = $copy->optionGroups()->with('values')->sole();
        $this->assertSame(['vi' => 'Màu', 'en' => 'Colour'], $group->getTranslations('name'));
        $this->assertSame(['red', 'blue'], $group->values->pluck('code')->all());
        $variants = $copy->variants()->with('optionValues')->get();
        $this->assertSame(['AO-1-RED-COPY', 'AO-1-BLUE-COPY'], $variants->pluck('sku')->all());
        $this->assertSame([0, 0], $variants->pluck('stock_quantity')->all());
        foreach ($variants as $variant) {
            $this->assertSame($group->id, $variant->optionValues->sole()->product_option_group_id);
            $this->assertSame(ProductVariant::signatureForOptionValueIds($variant->optionValues->pluck('id')->all()), $variant->option_signature);
        }

        // The original is untouched, and a second copy gets its own SKU.
        $this->assertSame([true, 7], [$original->fresh()->is_active, $original->fresh()->stock_quantity]);
        $second = app(ProductService::class)->duplicate($original);
        $this->assertSame('AO-1-COPY-2', $second->sku);
        $this->assertSame('AO-1-RED-COPY-2', $second->variants()->value('sku'));
    }

    public function test_duplicating_needs_the_create_permission_and_a_free_data_unit(): void
    {
        $original = $this->productWithVariants();

        $this->actingAs($this->admin(['products.view', 'products.update']))
            ->post("/vi/admin/products/{$original->id}/duplicate")->assertForbidden();

        ProjectSetting::query()->updateOrCreate(['setting_key' => PackageSync::SETTING_KEY], ['setting_value' => [
            'code' => 'TEST', 'name' => 'Gói test', 'limits' => ['data_unit' => 1, 'email_monthly' => null],
            'details' => [], 'plan_known' => true, 'synced_at' => now()->toIso8601String(),
        ]]);
        Cache::flush();
        $this->actingAs($this->admin(['products.view', 'products.create']))->from('/vi/admin/products')
            ->post("/vi/admin/products/{$original->id}/duplicate")->assertRedirect('/vi/admin/products');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_the_list_and_detail_pages_offer_it_only_to_those_who_may_create(): void
    {
        $original = $this->productWithVariants();
        $action = route('admin.products.duplicate', ['locale' => 'vi', 'product' => $original]);

        $this->actingAs($this->admin(['products.view', 'products.create']));
        $this->get('/vi/admin/products')->assertOk()->assertSee($action, false);
        $this->get("/vi/admin/products/{$original->id}")->assertOk()->assertSee($action, false);

        $this->actingAs($this->admin(['products.view']));
        $this->get('/vi/admin/products')->assertOk()->assertDontSee($action, false);
    }

    private function productWithVariants(): Product
    {
        $product = app(ProductService::class)->create([
            'name' => ['vi' => 'Áo thun', 'en' => 'T-shirt'],
            'description' => ['vi' => '<p>Vải cotton</p>', 'en' => '<p>Cotton</p>'],
            'meta_title' => ['vi' => 'Áo thun đẹp'],
            'sku' => 'AO-1',
            'image_url' => 'products/main.jpg',
            'price' => 250000,
            'stock_quantity' => 7,
            'is_active' => true,
            'is_featured' => true,
            'canonical_url' => 'https://shop.test/ao-thun',
        ]);
        $product->images()->createMany([['image_url' => 'products/a.jpg', 'sort_order' => 0], ['image_url' => 'products/b.jpg', 'sort_order' => 1]]);

        $group = $product->optionGroups()->create(['name' => ['vi' => 'Màu', 'en' => 'Colour'], 'code' => 'mau', 'sort_order' => 0]);
        foreach (['red' => 'Đỏ', 'blue' => 'Xanh'] as $code => $label) {
            $value = $group->values()->create(['label' => ['vi' => $label], 'code' => $code, 'is_active' => true]);
            $variant = $product->variants()->create([
                'name' => ['vi' => $label], 'sku' => 'AO-1-'.strtoupper($code), 'price' => 250000, 'stock_quantity' => 3, 'is_active' => true,
                'option_signature' => ProductVariant::signatureForOptionValueIds([$value->id]),
            ]);
            $variant->optionValues()->sync([$value->id]);
        }

        return $product->refresh();
    }

    private function admin(array $permissions): User
    {
        $role = Role::query()->create(['name' => 'Role '.uniqid(), 'permissions' => $permissions]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
