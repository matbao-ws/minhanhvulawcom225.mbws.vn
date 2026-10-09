<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\OrderStateTransitionService;
use App\Services\OrderStockService;
use App\Services\PaymentTransactionService;
use App\Services\PromotionService;

/**
 * What a cancelled order hands back. Creating an order takes stock, a voucher
 * use and promotion quota (OrderCreationService); cancelling used to return
 * only the stock, so an unpaid order held a limited voucher or a flash-sale
 * slot for good.
 *
 * The caller owns the transaction and has locked the order row, as it does
 * for creation.
 */
class OrderCancellationService
{
    public function __construct(
        private readonly OrderStockService $stock,
        private readonly PromotionService $promotions,
        private readonly OrderStateTransitionService $transitions,
        private readonly PaymentTransactionService $payments,
    ) {
    }

    /**
     * Cancel a locked order that is not cancelled yet and record why.
     *
     * @return bool whether the order was cancelled by this call
     */
    public function cancel(Order $order, string $note, ?int $userId = null): bool
    {
        $oldStatus = $order->status;
        if ($oldStatus === 'cancelled' || ! $this->transitions->canTransitionOrderStatus($oldStatus, 'cancelled')) {
            return false;
        }

        $order->update(['status' => 'cancelled']);
        $this->releaseReservations($order, $note);
        $this->payments->syncInitialTransaction($order);

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $oldStatus,
            'to_status' => 'cancelled',
            'from_payment_status' => $order->payment_status,
            'to_payment_status' => $order->payment_status,
            'note' => $note,
            'changed_by' => $userId,
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Return the stock, the voucher use and the promotion quota of a locked
     * order that has just been cancelled. Safe to repeat: stock has its own
     * idempotent ledger, the rest runs once per order.
     */
    public function releaseReservations(Order $order, string $stockNote = 'Hoàn kho khi hủy đơn'): void
    {
        $this->stock->restore($order, $stockNote);

        if ($order->reservations_released_at !== null) {
            return;
        }

        foreach ($order->loadMissing('items')->items as $item) {
            if ($item->promotion_id) {
                $this->promotions->release((int) $item->promotion_id, (int) $item->product_id, $item->product_variant_id ? (int) $item->product_variant_id : null, (int) $item->quantity);
            }
        }

        foreach (VoucherUsage::query()->where('order_id', $order->id)->get() as $usage) {
            $voucher = Voucher::query()->lockForUpdate()->find($usage->voucher_id);
            if ($voucher && $voucher->used_count > 0) {
                $voucher->decrement('used_count');
            }
            $usage->delete();
        }

        $order->forceFill(['reservations_released_at' => now()])->save();
    }
}
