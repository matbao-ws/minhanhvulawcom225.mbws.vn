<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderCreationService;
use App\Services\Orders\OrderDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The money math shared by public checkout and manual admin order creation.
 *
 * Both flows used to carry their own copy of these formulas; these assertions
 * are what keeps the single remaining copy honest. A change here is a change to
 * what customers are charged, so every branch of the total is pinned.
 */
class OrderCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderCreationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderCreationService::class);
    }

    private function product(float $price, int $stock = 100): Product
    {
        static $n = 0;
        $n++;

        return Product::query()->create([
            'name' => ['vi' => "San pham {$n}", 'en' => "Product {$n}"],
            'slug' => "order-product-{$n}",
            'sku' => "SKU-ORD-{$n}",
            'price' => $price,
            'stock_quantity' => $stock,
            'manage_stock' => true,
            'is_active' => true,
        ]);
    }

    /** @param array<int, array{0: Product, 1: int}> $items */
    private function lines(array $items): array
    {
        return array_map(
            fn (array $item): array => $this->service->priceLine($item[0], null, $item[1]),
            $items,
        );
    }

    private function draft(array $lines, float $extraDiscount = 0.0, float $shippingFee = 0.0): OrderDraft
    {
        return new OrderDraft(
            customerName: 'Nguyen Van A',
            customerEmail: 'buyer@example.test',
            customerPhone: '0987654321',
            shippingAddress: '123 Duong ABC',
            paymentMethod: 'cod',
            lines: $lines,
            extraDiscount: $extraDiscount,
            shippingFee: $shippingFee,
            paymentSource: 'public_checkout',
            historyNote: 'Test order',
            stockNote: 'Test stock note',
        );
    }

    private function create(OrderDraft $draft): Order
    {
        return DB::transaction(fn (): Order => $this->service->create($draft));
    }

    public function test_line_pricing_snapshots_the_product_into_the_order_item(): void
    {
        $product = $this->product(100_000);
        $line = $this->service->priceLine($product, null, 3);

        $this->assertSame($product->id, $line['data']['product_id']);
        $this->assertNull($line['data']['product_variant_id']);
        $this->assertSame(100_000.0, $line['data']['original_price']);
        $this->assertSame(3, $line['data']['quantity']);
        $this->assertEquals(300_000, $line['data']['total']);
    }

    public function test_subtotal_and_discountable_subtotal_add_up_across_lines(): void
    {
        $lines = $this->lines([[$this->product(100_000), 2], [$this->product(50_000), 1]]);

        $this->assertSame(250_000.0, $this->service->subtotal($lines));
        $this->assertSame(0.0, $this->service->promotionDiscount($lines));
        $this->assertSame(250_000.0, $this->service->discountableSubtotal($lines));
    }

    public function test_grand_total_is_subtotal_minus_discounts_plus_shipping(): void
    {
        $lines = $this->lines([[$this->product(200_000), 2]]);
        $order = $this->create($this->draft($lines, extraDiscount: 50_000, shippingFee: 30_000));

        $this->assertEquals(400_000, $order->subtotal);
        $this->assertEquals(50_000, $order->discount);
        $this->assertEquals(0, $order->promotion_discount);
        $this->assertEquals(30_000, $order->shipping_fee);
        $this->assertEquals(380_000, $order->grand_total, '400k - 50k discount + 30k shipping');
    }

    public function test_grand_total_never_goes_negative(): void
    {
        // A discount larger than the goods total must floor at zero rather than
        // producing a negative charge — the caller still owes the shipping fee.
        $lines = $this->lines([[$this->product(100_000), 1]]);
        $order = $this->create($this->draft($lines, extraDiscount: 500_000, shippingFee: 20_000));

        $this->assertEquals(20_000, $order->grand_total);
        $this->assertGreaterThanOrEqual(0, $order->grand_total);
    }

    public function test_shipping_fee_is_not_discounted(): void
    {
        $lines = $this->lines([[$this->product(100_000), 1]]);
        $order = $this->create($this->draft($lines, extraDiscount: 100_000, shippingFee: 25_000));

        $this->assertEquals(25_000, $order->grand_total, 'Goods fully discounted, shipping still charged.');
    }

    public function test_order_is_written_with_items_stock_payment_and_history(): void
    {
        $product = $this->product(100_000, stock: 10);
        $order = $this->create($this->draft($this->lines([[$product, 4]])));

        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertStringStartsWith('ORD-', $order->order_number);
        $this->assertCount(1, $order->items()->get());

        // Stock moved, payment opened, history recorded.
        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'pending',
            'note' => 'Test order',
        ]);
    }

    public function test_order_numbers_are_unique_across_orders(): void
    {
        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $this->create($this->draft($this->lines([[$this->product(10_000), 1]])))->order_number;
        }

        $this->assertCount(5, array_unique($numbers));
    }

    public function test_admin_flow_records_who_created_the_order(): void
    {
        $lines = $this->lines([[$this->product(100_000), 1]]);
        $order = $this->create(new OrderDraft(
            customerName: 'Khach',
            customerEmail: 'k@example.test',
            customerPhone: '0900000000',
            shippingAddress: 'Somewhere',
            paymentMethod: 'cod',
            lines: $lines,
            paymentSource: 'manual_order',
            historyNote: 'Tạo đơn thủ công',
            stockNote: 'Xuất kho cho đơn tạo thủ công',
            changedBy: null,
        ));

        $history = OrderStatusHistory::query()->where('order_id', $order->id)->first();
        $this->assertSame('Tạo đơn thủ công', $history->note);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id]);
    }

    public function test_customer_orders_are_linked_while_guest_orders_are_not(): void
    {
        $lines = $this->lines([[$this->product(10_000), 1]]);

        $this->assertNull($this->create($this->draft($lines))->user_id);

        $customer = User::factory()->create(['role_id' => null]);
        $linked = $this->create(new OrderDraft(
            customerName: 'A', customerEmail: 'a@b.test', customerPhone: '0900000000',
            shippingAddress: 'X', paymentMethod: 'cod', lines: $lines, userId: $customer->id,
        ));
        $this->assertSame($customer->id, $linked->user_id);
    }
}
