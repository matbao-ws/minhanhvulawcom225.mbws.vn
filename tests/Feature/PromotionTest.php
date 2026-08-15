<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Role;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalog', 'cart', 'cod_order'] as $featureCode) {
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $featureCode],
                ['is_enabled' => true],
            );
        }
    }

    public function test_higher_priority_automatic_promotion_wins_and_snapshots_the_discount_at_checkout(): void
    {
        PaymentMethod::query()->where('method_code', 'cod')->update(['status' => 'active']);
        $product = $this->product();

        $highPriority = Promotion::query()->create([
            'name' => ['vi' => 'Ưu đãi ưu tiên'],
            'kind' => 'automatic',
            'applies_to' => 'selected',
            'discount_type' => 'percentage',
            'value' => 10,
            'priority' => 20,
            'is_active' => true,
        ]);
        PromotionTarget::query()->create(['promotion_id' => $highPriority->id, 'product_id' => $product->id]);

        $lowerPriority = Promotion::query()->create([
            'name' => ['vi' => 'Ưu đãi rẻ hơn nhưng ưu tiên thấp'],
            'kind' => 'automatic',
            'applies_to' => 'selected',
            'discount_type' => 'fixed_price',
            'value' => 1,
            'priority' => 10,
            'is_active' => true,
        ]);
        PromotionTarget::query()->create(['promotion_id' => $lowerPriority->id, 'product_id' => $product->id]);

        $quote = app(PromotionService::class)->quote($product, null, 100000, 2);
        $this->assertSame($highPriority->id, $quote['promotion']->id);
        $this->assertSame(90000.0, $quote['unit_price']);

        $response = $this->postJson('/api/public/orders/checkout', [
            'customer_name' => 'Promotion Customer',
            'customer_email' => 'promotion@example.com',
            'customer_phone' => '0900000011',
            'shipping_address' => 'HCM',
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertOk()->assertJsonPath('data.promotion_discount', '20000.00');
        $order = Order::query()->where('customer_email', 'promotion@example.com')->with('items')->firstOrFail();
        $this->assertSame('20000.00', $order->promotion_discount);
        $this->assertSame('20000.00', $order->discount);
        $this->assertSame('100000.00', $order->items->first()->original_price);
        $this->assertSame('90000.00', $order->items->first()->price);
        $this->assertSame('20000.00', $order->items->first()->promotion_discount);
    }

    public function test_flash_sale_quota_is_enforced_without_blocking_regular_price_checkout(): void
    {
        PaymentMethod::query()->where('method_code', 'cod')->update(['status' => 'active']);
        $product = $this->product(['slug' => 'flash-sale-product', 'sku' => 'FLASH-01']);
        $promotion = Promotion::query()->create([
            'name' => ['vi' => 'Flash sale 10 giờ'],
            'kind' => 'flash_sale',
            'applies_to' => 'selected',
            'discount_type' => 'fixed_price',
            'value' => 50000,
            'quantity_limit' => 2,
            'is_active' => true,
        ]);
        $target = PromotionTarget::query()->create([
            'promotion_id' => $promotion->id,
            'product_id' => $product->id,
            'quantity_limit' => 2,
        ]);

        $payload = [
            'customer_name' => 'Flash Customer',
            'customer_email' => 'flash@example.com',
            'customer_phone' => '0900000012',
            'shipping_address' => 'HCM',
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ];
        $this->postJson('/api/public/orders/checkout', $payload)->assertOk();
        $this->assertSame(2, $promotion->fresh()->used_count);
        $this->assertSame(2, $target->fresh()->used_count);

        $payload['customer_email'] = 'flash-second@example.com';
        $payload['items'][0]['quantity'] = 1;
        $this->postJson('/api/public/orders/checkout', $payload)
            ->assertOk()
            ->assertJsonPath('data.promotion_discount', '0.00')
            ->assertJsonPath('data.items.0.price', '100000.00');
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(2, $promotion->fresh()->used_count);
        $this->assertSame(2, $target->fresh()->used_count);
    }

    public function test_admin_can_create_a_selected_product_promotion(): void
    {
        $role = Role::query()->create(['name' => 'Marketing Admin', 'permissions' => ['*']]);
        $admin = User::factory()->create(['role_id' => $role->id]);
        $product = $this->product(['slug' => 'admin-promotion-product', 'sku' => 'ADMIN-PROMO']);

        $this->actingAs($admin)->post('/vi/admin/promotions', [
            'name' => 'Ngày hội mua sắm',
            'kind' => 'automatic',
            'applies_to' => 'selected',
            'discount_type' => 'percentage',
            'value' => 15,
            'min_quantity' => 1,
            'priority' => 5,
            'is_active' => true,
            'target_product_ids' => [$product->id],
        ])->assertRedirect();

        $promotion = Promotion::query()->where('name->vi', 'Ngày hội mua sắm')->firstOrFail();
        $this->assertDatabaseHas('promotion_targets', ['promotion_id' => $promotion->id, 'product_id' => $product->id]);
    }

    public function test_admin_can_open_promotions_when_vouchers_are_disabled(): void
    {
        FeatureSetting::query()->updateOrCreate(
            ['feature_code' => 'voucher'],
            ['is_enabled' => false],
        );
        $role = Role::query()->create([
            'name' => 'Promotion Admin',
            'permissions' => ['vouchers.view', 'vouchers.create', 'vouchers.update', 'vouchers.delete', 'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete'],
        ]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($admin)->get('/vi/admin/promotions/');

        $response->assertOk();
        $response->assertViewIs('admin.promotions.index');
    }

    private function product(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => ['vi' => 'Sản phẩm khuyến mãi'],
            'slug' => 'promotion-product-'.str()->random(8),
            'sku' => 'PROMO-'.str()->upper(str()->random(8)),
            'price' => 100000,
            'stock_quantity' => 20,
            'manage_stock' => true,
            'is_active' => true,
        ], $overrides));
    }
}
