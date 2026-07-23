<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\ProductOptionService;
use App\Services\Catalog\ProductService;
use App\Services\OrderStockService;
use Database\Seeders\VariantV2DemoSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_generates_the_full_option_matrix_once_and_prevents_manual_duplicates(): void
    {
        $product = $this->product();
        $options = app(ProductOptionService::class);
        $variants = app(ProductService::class);

        $options->sync($product, [
            [
                'name' => 'Màu sắc',
                'display_type' => 'color',
                'values' => [
                    ['label' => 'Đỏ', 'color_hex' => '#ff0000', 'is_active' => true],
                    ['label' => 'Xanh', 'color_hex' => '#0000ff', 'is_active' => true],
                ],
            ],
            [
                'name' => 'Kích thước',
                'display_type' => 'select',
                'values' => [
                    ['label' => 'M', 'is_active' => true],
                    ['label' => 'L', 'is_active' => true],
                ],
            ],
        ]);

        $this->assertSame(4, $variants->generateVariants($product));
        $this->assertSame(0, $variants->generateVariants($product));
        $this->assertDatabaseCount('product_variants', 4);
        $this->assertDatabaseCount('product_variant_option_values', 8);
        $this->assertSame(4, $product->variants()->distinct('option_signature')->count('option_signature'));
        $this->assertSame(1, $product->variants()->where('is_default', true)->count());

        $ids = $product->optionGroups()->with('values')->get()->flatMap->values->pluck('id')->all();
        $this->expectException(DomainException::class);
        $variants->createVariant($product, [
            'sku' => 'MATRIX-DUPLICATE',
            'option_value_ids' => [$ids[0], $ids[2]],
            'stock_quantity' => 0,
            'is_active' => true,
        ]);
    }

    public function test_used_option_values_cannot_be_silently_deleted(): void
    {
        $product = $this->product();
        $options = app(ProductOptionService::class);
        $variants = app(ProductService::class);

        $options->sync($product, [[
            'name' => 'Màu sắc',
            'display_type' => 'select',
            'values' => [
                ['label' => 'Đỏ', 'is_active' => true],
                ['label' => 'Xanh', 'is_active' => true],
            ],
        ]]);
        $group = $product->optionGroups()->with('values')->firstOrFail();
        $variants->createVariant($product, [
            'sku' => 'SAFE-RED',
            'option_value_ids' => [$group->values->first()->id],
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        $this->expectException(DomainException::class);
        $options->sync($product, [[
            'id' => $group->id,
            'name' => 'Màu sắc',
            'display_type' => 'select',
            'values' => [[
                'id' => $group->values->last()->id,
                'label' => 'Xanh',
                'is_active' => true,
            ]],
        ]]);
    }

    public function test_admin_can_create_product_and_its_sku_matrix_from_the_create_form(): void
    {
        FeatureSetting::query()->create(['feature_code' => 'catalog', 'is_enabled' => true]);
        $role = Role::query()->create(['name' => 'Catalog Admin', 'permissions' => ['*']]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)
            ->get('/vi/admin/products/create')
            ->assertOk()
            ->assertSee('has_variants');

        $this->actingAs($admin)->post('/vi/admin/products', [
            'name' => 'Áo thun Variant V2',
            'slug' => 'ao-thun-variant-v2',
            'sku' => 'TSHIRT-V2',
            'price' => 199000,
            'stock_quantity' => 0,
            'manage_stock' => true,
            'is_active' => true,
            'has_variants' => true,
            'variant_groups' => [
                [
                    'name' => 'Màu sắc',
                    'display_type' => 'color',
                    'values' => [
                        ['label' => 'Đỏ', 'color_hex' => '#ff0000', 'is_active' => true],
                        ['label' => 'Xanh', 'color_hex' => '#0000ff', 'is_active' => true],
                    ],
                ],
                [
                    'name' => 'Kích thước',
                    'display_type' => 'select',
                    'values' => [
                        ['label' => 'M', 'is_active' => true],
                        ['label' => 'L', 'is_active' => true],
                    ],
                ],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $product = Product::query()->where('slug', 'ao-thun-variant-v2')->firstOrFail();
        $this->assertSame(2, $product->optionGroups()->count());
        $this->assertSame(4, $product->variants()->count());
        $this->assertSame(8, \Illuminate\Support\Facades\DB::table('product_variant_option_values')->count());

        $this->actingAs($admin)
            ->get("/vi/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertSee('variant_groups', false)
            ->assertSee('Màu sắc');

        $groups = $product->optionGroups()->with('values')->get()->map(fn ($group) => [
            'id' => $group->id,
            'name' => $group->name,
            'display_type' => $group->display_type,
            'values' => $group->values->map(fn ($value) => [
                'id' => $value->id,
                'label' => $value->label,
                'color_hex' => $value->color_hex,
                'is_active' => $value->is_active,
            ])->all(),
        ])->all();
        $groups[0]['values'][] = ['label' => 'Be', 'color_hex' => '#d4c5a9', 'is_active' => true];

        $this->actingAs($admin)->put("/vi/admin/products/{$product->id}", [
            'name' => 'Áo thun Variant V2',
            'slug' => 'ao-thun-variant-v2',
            'sku' => 'TSHIRT-V2',
            'price' => 199000,
            'stock_quantity' => 0,
            'manage_stock' => true,
            'is_active' => true,
            'has_variants' => true,
            'variant_groups' => $groups,
        ])->assertRedirect();

        $this->assertSame(3, $product->optionGroups()->firstOrFail()->values()->count());
        $this->assertSame(6, $product->variants()->count());
    }

    public function test_legacy_inventory_movement_restores_both_stock_fields(): void
    {
        $product = $this->product(['stock_quantity' => 1]);
        $options = app(ProductOptionService::class);
        $options->sync($product, [[
            'name' => 'Phiên bản',
            'display_type' => 'select',
            'values' => [['label' => 'A', 'is_active' => true]],
        ]]);
        $value = $product->optionGroups()->with('values')->firstOrFail()->values->first();
        $variant = app(ProductService::class)->createVariant($product, [
            'sku' => 'LEGACY-A',
            'option_value_ids' => [$value->id],
            'stock_quantity' => 1,
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-LEGACY-V2',
            'customer_name' => 'Legacy',
            'customer_email' => 'legacy@example.com',
            'customer_phone' => '0900000000',
            'shipping_address' => 'HCM',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
        ]);
        // No inventory_source key means this is a ledger entry written before V2.
        \App\Models\InventoryMovement::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'action' => 'sale',
            'direction' => 'out',
            'quantity' => 1,
            'product_stock_after' => 1,
            'variant_stock_after' => 1,
            'idempotency_key' => "order:{$order->id}:item:{$item->id}:sale",
            'metadata' => [],
        ]);

        app(OrderStockService::class)->restore($order);

        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertSame(2, $variant->fresh()->stock_quantity);
    }

    public function test_demo_variant_seeder_creates_a_complex_product_without_duplicates_when_re_run(): void
    {
        $this->seed(VariantV2DemoSeeder::class);
        $this->seed(VariantV2DemoSeeder::class);

        $product = Product::query()->where('slug', 'ao-khoac-outdoor-variant-v2-demo')->firstOrFail();
        $this->assertSame(3, $product->optionGroups()->count());
        $this->assertSame(10, $product->optionGroups()->withCount('values')->get()->sum('values_count'));
        $this->assertSame(32, $product->variants()->count());
        $this->assertSame(32, $product->variants()->distinct('option_signature')->count('option_signature'));
    }

    private function product(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => ['vi' => 'Sản phẩm Variant V2'],
            'slug' => 'variant-v2-'.str()->random(8),
            'sku' => 'V2-'.str()->upper(str()->random(8)),
            'price' => 100,
            'stock_quantity' => 0,
            'manage_stock' => true,
            'is_active' => true,
        ], $overrides));
    }
}
