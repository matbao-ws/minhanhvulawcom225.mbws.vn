<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureSetting::query()->create(['feature_code' => 'catalog', 'is_enabled' => true]);
        $role = Role::query()->create(['name' => 'Admin', 'permissions' => ['*']]);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
        $this->product = Product::query()->create([
            'name' => ['vi' => 'Sản phẩm kiểm thử'],
            'slug' => 'san-pham-kiem-thu',
            'sku' => 'LIFECYCLE-01',
            'price' => 100000,
            'stock_quantity' => 3,
            'manage_stock' => true,
            'is_active' => true,
        ]);
        $group = ProductOptionGroup::query()->create([
            'product_id' => $this->product->id,
            'name' => ['vi' => 'Phiên bản'],
            'code' => 'version',
        ]);
        $value = ProductOptionValue::query()->create([
            'product_option_group_id' => $group->id,
            'label' => ['vi' => 'Kiểm thử'],
            'code' => 'test',
            'is_active' => true,
        ]);
        $this->variant = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'name' => ['vi' => 'Biến thể kiểm thử'],
            'sku' => 'LIFECYCLE-01-VAR',
            'option_signature' => ProductVariant::signatureForOptionValueIds([$value->id]),
            'stock_quantity' => 3,
            'is_active' => true,
        ]);
        $this->variant->optionValues()->sync([$value->id]);
    }

    public function test_cancelling_an_order_restores_stock_once_and_records_history(): void
    {
        Mail::fake();
        $order = $this->makeOrder(2, 'pending', 'pending');
        $this->product->decrement('stock_quantity', 2);

        $this->actingAs($this->admin)->patch("/vi/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
            'payment_status' => 'failed',
            'note' => 'Khách đổi ý',
        ])->assertRedirect();

        $this->assertSame(3, $this->product->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'from_status' => 'pending', 'to_status' => 'cancelled']);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'status_changed', 'subject_id' => $order->id]);

        $this->actingAs($this->admin)->patch("/vi/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
            'payment_status' => 'failed',
        ])->assertRedirect();
        $this->assertSame(3, $this->product->fresh()->stock_quantity);
    }

    public function test_admin_cannot_reopen_a_cancelled_order(): void
    {
        $order = $this->makeOrder(1, 'cancelled', 'failed');

        $this->actingAs($this->admin)
            ->from("/vi/admin/orders/{$order->id}")
            ->patch("/vi/admin/orders/{$order->id}/status", [
                'status' => 'pending',
                'payment_status' => 'pending',
            ])
            ->assertRedirect("/vi/admin/orders/{$order->id}")
            ->assertSessionHasErrors('status');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_partial_refund_restores_only_refunded_quantity_and_prevents_over_refund(): void
    {
        $order = $this->makeOrder(2, 'processing', 'paid');
        $item = $order->items()->firstOrFail();
        $this->product->decrement('stock_quantity', 2);

        $this->actingAs($this->admin)->post("/vi/admin/orders/{$order->id}/refund", [
            'type' => 'partial',
            'reason' => 'Lỗi một sản phẩm',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertRedirect();

        $this->assertSame(2, $this->product->fresh()->stock_quantity);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
        $this->assertDatabaseHas('order_refunds', ['order_id' => $order->id, 'amount' => 100000]);

        $this->actingAs($this->admin)->from("/vi/admin/orders/{$order->id}")->post("/vi/admin/orders/{$order->id}/refund", [
            'type' => 'partial',
            'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
        ])->assertRedirect("/vi/admin/orders/{$order->id}")->assertSessionHasErrors('refund');
        $this->assertSame(2, $this->product->fresh()->stock_quantity);
    }

    public function test_admin_can_create_manual_order_with_inventory_and_initial_history(): void
    {
        $this->actingAs($this->admin)->post('/vi/admin/orders', [
            'customer_name' => 'Manual Customer',
            'customer_email' => 'manual@example.com',
            'customer_phone' => '0900000000',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'cod',
            'discount' => 0,
            'shipping_fee' => 30000,
            'items' => [['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 1]],
        ])->assertRedirect();

        $order = Order::query()->where('customer_email', 'manual@example.com')->firstOrFail();
        $this->assertSame(3, $this->product->fresh()->stock_quantity);
        $this->assertSame(2, $this->variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'to_status' => 'pending']);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'created', 'subject_id' => $order->id]);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'payment_method' => 'cod',
            'status' => 'pending',
        ]);
    }

    public function test_manual_order_snapshots_and_reserves_an_active_sku_promotion(): void
    {
        $promotion = Promotion::query()->create([
            'name' => ['vi' => 'Ưu đãi đơn thủ công'],
            'kind' => 'flash_sale',
            'applies_to' => 'selected',
            'discount_type' => 'percentage',
            'value' => 20,
            'quantity_limit' => 1,
            'is_active' => true,
        ]);
        $target = PromotionTarget::query()->create([
            'promotion_id' => $promotion->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'quantity_limit' => 1,
        ]);

        $this->actingAs($this->admin)->post('/vi/admin/orders', [
            'customer_name' => 'Promotion Manual Customer',
            'customer_email' => 'promotion-manual@example.com',
            'customer_phone' => '0900000007',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'cod',
            'discount' => 0,
            'shipping_fee' => 0,
            'items' => [['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 1]],
        ])->assertRedirect();

        $order = Order::query()->where('customer_email', 'promotion-manual@example.com')->with('items')->firstOrFail();
        $item = $order->items->first();
        $this->assertSame('20000.00', $order->promotion_discount);
        $this->assertSame('20000.00', $order->discount);
        $this->assertSame('80000.00', $order->grand_total);
        $this->assertSame('100000.00', $item->original_price);
        $this->assertSame('80000.00', $item->price);
        $this->assertSame($promotion->id, $item->promotion_id);
        $this->assertSame(1, $promotion->fresh()->used_count);
        $this->assertSame(1, $target->fresh()->used_count);
    }

    public function test_variant_inventory_is_restored_only_once_across_refund_and_cancellation(): void
    {
        $this->actingAs($this->admin)->post('/vi/admin/orders', [
            'customer_name' => 'Variant Customer',
            'customer_email' => 'variant@example.com',
            'customer_phone' => '0900000002',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'cod',
            'discount' => 0,
            'shipping_fee' => 0,
            'items' => [[
                'product_id' => $this->product->id,
                'variant_id' => $this->variant->id,
                'quantity' => 2,
            ]],
        ])->assertRedirect();

        $order = Order::query()->where('customer_email', 'variant@example.com')->firstOrFail();
        $orderItem = $order->items()->firstOrFail();
        $this->assertSame(3, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, $this->variant->fresh()->stock_quantity);

        $this->actingAs($this->admin)->patch("/vi/admin/orders/{$order->id}/status", [
            'status' => 'pending',
            'payment_status' => 'paid',
        ])->assertRedirect();

        $this->actingAs($this->admin)->post("/vi/admin/orders/{$order->id}/refund", [
            'type' => 'partial',
            'items' => [['order_item_id' => $orderItem->id, 'quantity' => 1]],
        ])->assertRedirect();
        $this->assertSame(3, $this->product->fresh()->stock_quantity);
        $this->assertSame(2, $this->variant->fresh()->stock_quantity);

        $this->actingAs($this->admin)->patch("/vi/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
            'payment_status' => 'partially_refunded',
        ])->assertRedirect();
        $this->assertSame(3, $this->product->fresh()->stock_quantity);
        $this->assertSame(3, $this->variant->fresh()->stock_quantity);
        $this->assertSame(3, InventoryMovement::query()->where('order_item_id', $orderItem->id)->count());
        $this->assertDatabaseHas('inventory_movements', [
            'order_item_id' => $orderItem->id,
            'action' => 'cancellation',
            'direction' => 'in',
            'quantity' => 1,
        ]);
    }

    private function makeOrder(int $quantity, string $status, string $paymentStatus): Order
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-LIFE-'.strtoupper(str()->random(8)),
            'customer_name' => 'Lifecycle Customer',
            'customer_email' => 'lifecycle@example.com',
            'customer_phone' => '0900000001',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'cod',
            'payment_status' => $paymentStatus,
            'status' => $status,
            'subtotal' => 100000 * $quantity,
            'discount' => 0,
            'grand_total' => 100000 * $quantity,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'price' => 100000,
            'quantity' => $quantity,
            'total' => 100000 * $quantity,
        ]);

        return $order;
    }
}
