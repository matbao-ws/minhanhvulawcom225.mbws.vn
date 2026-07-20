<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use DomainException;

class OrderStockService
{
    public function deduct(Order $order, OrderItem $orderItem, string $note = 'Xuất kho cho đơn hàng'): void
    {
        $quantity = (int) $orderItem->quantity;
        if (! $orderItem->product_id || $quantity < 1) {
            return;
        }

        $idempotencyKey = "order:{$order->id}:item:{$orderItem->id}:sale";
        if (InventoryMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $product = Product::query()->lockForUpdate()->find($orderItem->product_id);
        if (! $product || ! $product->manage_stock) {
            return;
        }

        $variant = $this->lockedVariant($orderItem, $product);
        $inventorySource = $variant && $product->usesVariantInventory() ? 'variant' : 'product';
        if ($inventorySource === 'product' && $product->stock_quantity < $quantity) {
            throw new DomainException("{$product->name} không đủ tồn kho.");
        }
        if ($inventorySource === 'variant' && $variant->stock_quantity < $quantity) {
            throw new DomainException("Biến thể {$variant->name} không đủ tồn kho.");
        }

        $movement = InventoryMovement::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            $this->movementPayload(
                order: $order,
                orderItem: $orderItem,
                product: $product,
                variant: $variant,
                action: 'sale',
                direction: 'out',
                quantity: $quantity,
                productStockAfter: $inventorySource === 'variant' ? $product->stock_quantity : $product->stock_quantity - $quantity,
                variantStockAfter: $inventorySource === 'variant' ? $variant->stock_quantity - $quantity : null,
                inventorySource: $inventorySource,
                note: $note,
            ),
        );

        if (! $movement->wasRecentlyCreated) {
            return;
        }

        if ($inventorySource === 'variant') {
            $variant->decrement('stock_quantity', $quantity);
        } else {
            $product->decrement('stock_quantity', $quantity);
        }
    }

    public function restore(Order $order, string $note = 'Hoàn kho khi hủy đơn'): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $this->restoreItem(
                $order,
                $item,
                (int) $item->quantity,
                'cancellation',
                "order:{$order->id}:item:{$item->id}:cancellation",
                $note,
            );
        }
    }

    public function restorePartial(Order $order, array $refundItems, int $refundId, string $note = 'Hoàn kho theo hoàn tiền'): void
    {
        $order->loadMissing('items');
        $itemsById = $order->items->keyBy('id');

        collect($refundItems)
            ->groupBy('order_item_id')
            ->each(function ($items, $orderItemId) use ($order, $itemsById, $refundId, $note): void {
                $item = $itemsById->get($orderItemId);
                if (! $item) {
                    return;
                }

                $quantity = $items->sum(fn (array $refundItem) => (int) $refundItem['quantity']);
                $this->restoreItem(
                    $order,
                    $item,
                    $quantity,
                    'refund',
                    "order:{$order->id}:item:{$item->id}:refund:{$refundId}",
                    $note,
                );
            });
    }

    private function restoreItem(
        Order $order,
        OrderItem $orderItem,
        int $requestedQuantity,
        string $action,
        string $idempotencyKey,
        string $note,
    ): void {
        if (! $orderItem->product_id || $requestedQuantity < 1) {
            return;
        }

        if (InventoryMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $product = Product::query()->lockForUpdate()->find($orderItem->product_id);
        if (! $product || ! $product->manage_stock) {
            return;
        }

        $variant = $this->lockedVariant($orderItem, $product);
        $inventorySource = $this->restoreInventorySource($orderItem, $product, $variant);
        $quantity = min($requestedQuantity, $this->restorableQuantity($orderItem));
        if ($quantity < 1) {
            return;
        }

        $movement = InventoryMovement::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            $this->movementPayload(
                order: $order,
                orderItem: $orderItem,
                product: $product,
                variant: $variant,
                action: $action,
                direction: 'in',
                quantity: $quantity,
                productStockAfter: $inventorySource === 'variant' ? $product->stock_quantity : $product->stock_quantity + $quantity,
                variantStockAfter: in_array($inventorySource, ['variant', 'legacy_both'], true) ? $variant?->stock_quantity + $quantity : null,
                inventorySource: $inventorySource,
                note: $note,
            ),
        );

        if (! $movement->wasRecentlyCreated) {
            return;
        }

        if ($inventorySource === 'variant') {
            $variant->increment('stock_quantity', $quantity);
        } elseif ($inventorySource === 'legacy_both') {
            $product->increment('stock_quantity', $quantity);
            $variant?->increment('stock_quantity', $quantity);
        } else {
            $product->increment('stock_quantity', $quantity);
        }
    }

    private function lockedVariant(OrderItem $orderItem, Product $product): ?ProductVariant
    {
        if (! $orderItem->product_variant_id) {
            return null;
        }

        return ProductVariant::query()
            ->whereKey($orderItem->product_variant_id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();
    }

    private function restorableQuantity(OrderItem $orderItem): int
    {
        $movements = InventoryMovement::query()->where('order_item_id', $orderItem->id);
        $soldQuantity = (int) (clone $movements)->where('direction', 'out')->sum('quantity');
        $restoredQuantity = (int) (clone $movements)->where('direction', 'in')->sum('quantity');

        // Orders created before the inventory ledger have no sale movement.
        $trackedQuantity = $soldQuantity > 0 ? $soldQuantity : (int) $orderItem->quantity;

        return max(0, $trackedQuantity - $restoredQuantity);
    }

    private function restoreInventorySource(OrderItem $orderItem, Product $product, ?ProductVariant $variant): string
    {
        $saleMovement = InventoryMovement::query()
            ->where('order_item_id', $orderItem->id)
            ->where('direction', 'out')
            ->latest('id')
            ->first();

        $source = data_get($saleMovement?->metadata, 'inventory_source');
        if (in_array($source, ['product', 'variant'], true)) {
            return $source;
        }

        // Pre-V2 ledger entries with a variant reduced both fields. Retain that
        // historical restoration behavior while all new movements declare source.
        if ($variant && $product->usesVariantInventory()) {
            return 'legacy_both';
        }

        return 'product';
    }

    private function movementPayload(
        Order $order,
        OrderItem $orderItem,
        Product $product,
        ?ProductVariant $variant,
        string $action,
        string $direction,
        int $quantity,
        int $productStockAfter,
        ?int $variantStockAfter,
        string $inventorySource,
        string $note,
    ): array {
        return [
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'action' => $action,
            'direction' => $direction,
            'quantity' => $quantity,
            'product_stock_after' => $productStockAfter,
            'variant_stock_after' => $variantStockAfter,
            'note' => $note,
            'metadata' => [
                'order_number' => $order->order_number,
                'sku' => $orderItem->sku,
                'inventory_source' => $inventorySource,
            ],
            'created_by' => auth()->id(),
            'created_at' => now(),
        ];
    }
}
