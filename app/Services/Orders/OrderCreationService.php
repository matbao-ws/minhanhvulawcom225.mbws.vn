<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\LocalizedContent;
use App\Services\OrderStockService;
use App\Services\PaymentTransactionService;
use App\Services\PromotionService;
use Illuminate\Support\Str;

/**
 * The single definition of how an order's money is computed and written.
 *
 * Two flows create orders — public checkout and manual admin creation — and
 * they legitimately differ in how a variant is chosen (option values vs. a
 * picked SKU), where the second discount comes from (voucher vs. manual) and
 * how the shipping fee is decided. Everything after that is identical, and a
 * divergence in it would be a money bug, so it lives here rather than being
 * copied into each controller.
 *
 * Callers own the surrounding `DB::transaction()` and row locks; every method
 * here expects to run inside one.
 */
class OrderCreationService
{
    public function __construct(
        private readonly PromotionService $promotions,
        private readonly OrderStockService $stock,
        private readonly PaymentTransactionService $payments,
        private readonly LocalizedContent $localizedContent,
    ) {}

    /**
     * Price one order line: resolve the promotion and build the order_items
     * payload. The caller has already decided which variant applies.
     *
     * @return array{quote: array, data: array<string, mixed>}
     */
    public function priceLine(Product $product, ?ProductVariant $variant, int $quantity): array
    {
        $price = $variant?->price !== null ? (float) $variant->price : (float) $product->price;
        $quote = $this->promotions->quote($product, $variant, $price, $quantity);

        return [
            'quote' => $quote,
            'data' => [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'promotion_id' => $quote['promotion']?->id,
                'product_name' => $this->localizedContent->get($product, 'name'),
                'variant_name' => $variant ? $this->localizedContent->get($variant, 'name') : null,
                'promotion_name' => $quote['promotion'] ? $this->localizedContent->get($quote['promotion'], 'name') : null,
                'sku' => $variant?->sku ?: $product->sku,
                'original_price' => $price,
                'promotion_discount' => $quote['discount'],
                'price' => $quote['unit_price'],
                'quantity' => $quantity,
                'total' => $quote['unit_price'] * $quantity,
            ],
        ];
    }

    /**
     * Goods total before any discount.
     *
     * @param  array<int, array{quote: array, data: array<string, mixed>}>  $lines
     */
    public function subtotal(array $lines): float
    {
        return array_sum(array_map(
            static fn (array $line): float => (float) $line['data']['original_price'] * (int) $line['data']['quantity'],
            $lines,
        ));
    }

    /**
     * Total automatic-promotion discount across the lines.
     *
     * @param  array<int, array{quote: array, data: array<string, mixed>}>  $lines
     */
    public function promotionDiscount(array $lines): float
    {
        return array_sum(array_map(
            static fn (array $line): float => (float) $line['quote']['discount'],
            $lines,
        ));
    }

    /**
     * Amount a voucher or a manual discount may be applied to: goods total
     * after automatic promotions, never negative.
     *
     * @param  array<int, array{quote: array, data: array<string, mixed>}>  $lines
     */
    public function discountableSubtotal(array $lines): float
    {
        return max(0.0, $this->subtotal($lines) - $this->promotionDiscount($lines));
    }

    /**
     * Create the order with its items, reserve promotions, move stock, open a
     * pending payment transaction and record the opening status history.
     */
    public function create(OrderDraft $draft): Order
    {
        $subtotal = $this->subtotal($draft->lines);
        $promotionDiscount = $this->promotionDiscount($draft->lines);

        $order = Order::query()->create([
            'order_number' => $this->generateOrderNumber(),
            'locale' => $draft->locale ?? app()->getLocale(),
            'customer_name' => $draft->customerName,
            'customer_email' => $draft->customerEmail,
            'customer_phone' => $draft->customerPhone,
            'shipping_address' => $draft->shippingAddress,
            'payment_method' => $draft->paymentMethod,
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => $subtotal,
            'discount' => $promotionDiscount + $draft->extraDiscount,
            'promotion_discount' => $promotionDiscount,
            'shipping_fee' => $draft->shippingFee,
            'grand_total' => max(0.0, $subtotal - $promotionDiscount - $draft->extraDiscount) + $draft->shippingFee,
            'notes' => $draft->notes,
            'user_id' => $draft->userId,
        ]);

        foreach ($draft->lines as $line) {
            $orderItem = $order->items()->create($line['data']);
            $this->promotions->reserve($line['quote'], (int) $orderItem->quantity);
            $this->stock->deduct($order, $orderItem, $draft->stockNote);
        }

        $this->payments->createPending($order, $draft->paymentSource);

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'to_status' => 'pending',
            'to_payment_status' => 'pending',
            'note' => $draft->historyNote,
            'changed_by' => $draft->changedBy,
            'created_at' => now(),
        ]);

        return $order;
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = Order::NUMBER_PREFIX.strtoupper(Str::random(Order::NUMBER_RANDOM_LENGTH));
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
