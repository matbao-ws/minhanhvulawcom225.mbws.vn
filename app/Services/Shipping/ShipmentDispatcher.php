<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\ActivityLogger;
use App\Services\OrderStateTransitionService;
use App\Services\ShippingService;
use Illuminate\Support\Facades\DB;

/**
 * Hands an order to a carrier (GHTK, GHN) — from the admin "Tạo vận đơn" button or automatically
 * when an order is confirmed. One path for both: the order is claimed under a row lock
 * (shipping_pending) so two clicks, or a click racing the automatic push, never create two
 * shipments; on failure the claim is released and the order can be pushed again.
 */
class ShipmentDispatcher
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly OrderStateTransitionService $transitions,
    ) {}

    /**
     * @param  array<string, mixed>  $params  carrier fields: weight, to_district_id, to_ward_code, length… (GHN) or province, district, ward (GHTK)
     * @return array{success: bool, message: string, tracking_number?: string, fee?: float}
     */
    public function push(Order $order, string $carrier, array $params, ?int $actorId = null, string $note = ''): array
    {
        try {
            $claimed = DB::transaction(function () use ($order): Order {
                $locked = Order::query()->with('items.variant')->lockForUpdate()->findOrFail($order->id);
                if ($locked->tracking_number || $locked->shipping_status === 'shipping_pending') {
                    throw new \DomainException('Đơn hàng đang hoặc đã được đẩy sang đơn vị vận chuyển.');
                }
                if (! Order::canTransitionShippingStatus($locked->shipping_status ?: 'not_shipped', 'shipping_pending')) {
                    throw new \DomainException('Trạng thái giao hàng hiện tại không thể đẩy đơn.');
                }
                if (! $this->transitions->canTransitionOrderStatus($locked->status, 'processing')) {
                    throw new \DomainException('Trạng thái đơn hàng hiện tại không thể đẩy sang giao hàng.');
                }
                $locked->update(['shipping_status' => 'shipping_pending', 'shipping_status_updated_at' => now()]);

                return $locked->fresh(['items.variant']);
            }, 3);
        } catch (\DomainException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        $result = $carrier === 'ghn'
            ? $this->shipping->pushToGHN($claimed, $params)
            : $this->shipping->pushToGHTK($claimed, $params);

        if (! $result['success']) {
            DB::transaction(function () use ($order): void {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
                if ($locked->shipping_status === 'shipping_pending' && ! $locked->tracking_number) {
                    $locked->update(['shipping_status' => 'not_shipped', 'shipping_status_updated_at' => now()]);
                }
            });

            return $result;
        }

        try {
            DB::transaction(function () use ($order, $carrier, $result, $actorId, $note): void {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
                $oldStatus = $locked->status;
                $this->transitions->assertCanTransition($oldStatus, 'processing', $locked->payment_status, $locked->payment_status);
                $locked->update([
                    'shipping_carrier' => $carrier,
                    'carrier_shipping_fee' => $result['fee'],
                    'tracking_number' => $result['tracking_number'],
                    'shipping_status' => 'shipping_created',
                    'shipping_status_updated_at' => now(),
                    'status' => 'processing',
                ]);

                if ($oldStatus !== 'processing') {
                    OrderStatusHistory::query()->create([
                        'order_id' => $locked->id,
                        'from_status' => $oldStatus,
                        'to_status' => 'processing',
                        'from_payment_status' => $locked->payment_status,
                        'to_payment_status' => $locked->payment_status,
                        'note' => $note ?: 'Đẩy đơn sang '.strtoupper($carrier),
                        'changed_by' => $actorId,
                        'created_at' => now(),
                    ]);
                }
                ActivityLogger::log('shipping_pushed', $locked, "Đẩy đơn hàng {$locked->order_number} sang ".strtoupper($carrier), [
                    'tracking_number' => $result['tracking_number'],
                    'carrier_shipping_fee' => $result['fee'],
                    'automatic' => $note !== '',
                ]);
            });
        } catch (\DomainException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        return $result;
    }

    /**
     * After an admin confirms an order: create its GHN shipment when the shop turned that on and
     * the order carries the GHN address codes chosen at checkout.
     *
     * @return array{success: bool, message: string, tracking_number?: string}|null null when it does not apply
     */
    public function autoPushOnConfirm(Order $order, ?int $actorId = null): ?array
    {
        $ghn = data_get($this->shipping->getSettings(), 'ghn', []);
        $location = $order->ghnLocation();

        if (! data_get($ghn, 'enabled') || ! data_get($ghn, 'auto_push_on_confirm') || $location === null
            || $order->tracking_number || $order->status !== 'processing') {
            return null;
        }

        return $this->push($order, 'ghn', [
            'to_district_id' => (int) $location['district_id'],
            'to_ward_code' => (string) $location['ward_code'],
            'weight' => $this->weight($order, (int) data_get($ghn, 'default_weight', 500)),
            'length' => (int) data_get($ghn, 'default_length', 20),
            'width' => (int) data_get($ghn, 'default_width', 15),
            'height' => (int) data_get($ghn, 'default_height', 10),
        ], $actorId, 'Tự tạo vận đơn GHN khi xác nhận đơn');
    }

    /** Grams: the variants' weights when every line has one, otherwise the shop's default. */
    public function weight(Order $order, int $default): int
    {
        $order->loadMissing('items.variant');
        $total = 0;
        foreach ($order->items as $item) {
            $grams = (int) ($item->variant?->weight_grams ?? 0);
            if ($grams < 1) {
                return max(1, $default);
            }
            $total += $grams * (int) $item->quantity;
        }

        return max(1, min(50000, $total ?: $default));
    }
}
