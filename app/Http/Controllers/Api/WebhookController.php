<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderStatusEmail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ShippingPartner;
use App\Services\OrderStateTransitionService;
use App\Services\OrderStockService;
use App\Services\PaymentTransactionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly OrderStockService $orderStockService,
        private readonly OrderStateTransitionService $orderStateTransitionService,
        private readonly PaymentTransactionService $paymentTransactionService,
    ) {
    }

    public function handleGHTK(Request $request)
    {
        $ghtk = ShippingPartner::query()->where('partner_code', 'DTGH000012')->first();
        $configuredToken = (string) data_get($ghtk?->settings, 'webhook_token', '');
        $trackingEnabled = (bool) data_get($ghtk?->settings, 'realtime_tracking_enabled', false);
        $providedToken = (string) $request->header('X-GHTK-Token');
        if ($providedToken === '' && config('app.ghtk_webhook_allow_query_token')) {
            $providedToken = (string) $request->query('token', '');
        }

        if (! $trackingEnabled || $configuredToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            Log::warning('GHTK webhook rejected due to invalid credentials.');

            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $payload = $request->validate([
            'partner_id' => ['required', 'string', 'max:100'],
            'label_id' => ['nullable', 'string', 'max:100'],
            'status_id' => ['nullable', 'integer', 'between:-1,21'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $order = Order::query()->where('order_number', $payload['partner_id'])->first();
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $fingerprint = hash('sha256', implode('|', [
            'ghtk', $payload['partner_id'], $payload['label_id'] ?? '', $payload['status_id'] ?? '', $payload['fee'] ?? '', $payload['reason'] ?? '',
        ]));

        try {
            $result = DB::transaction(function () use ($order, $payload, $fingerprint): array {
                $lockedOrder = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);

                if (DB::table('shipping_webhook_events')->where('fingerprint', $fingerprint)->exists()) {
                    return ['order' => $lockedOrder, 'duplicate' => true, 'status_changed' => false];
                }
                DB::table('shipping_webhook_events')->insert([
                    'order_id' => $lockedOrder->id,
                    'provider' => 'ghtk',
                    'fingerprint' => $fingerprint,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'received_at' => now(),
                ]);

                $oldStatus = $lockedOrder->status;
                $oldPaymentStatus = $lockedOrder->payment_status;
                $oldShippingStatus = $lockedOrder->shipping_status ?: 'not_shipped';
                $requestedStatus = $this->orderStatusForGhtk($payload['status_id'] ?? null, $oldStatus);
                $requestedShippingStatus = Order::shippingStatusFromGhtk(isset($payload['status_id']) ? (int) $payload['status_id'] : null);

                $updates = ['shipping_carrier' => 'ghtk'];
                $statusChanged = false;
                $shippingChanged = false;
                if ($requestedStatus !== $oldStatus && $this->orderStateTransitionService->canTransitionOrderStatus($oldStatus, $requestedStatus)) {
                    $updates['status'] = $requestedStatus;
                    $statusChanged = true;
                }
                if ($requestedShippingStatus && $requestedShippingStatus !== $oldShippingStatus && Order::canTransitionShippingStatus($oldShippingStatus, $requestedShippingStatus)) {
                    $updates['shipping_status'] = $requestedShippingStatus;
                    $updates['shipping_status_updated_at'] = now();
                    $shippingChanged = true;
                }
                if (! empty($payload['label_id']) && $lockedOrder->tracking_number !== $payload['label_id']) {
                    $updates['tracking_number'] = $payload['label_id'];
                }
                if (array_key_exists('fee', $payload) && $payload['fee'] !== null && (float) $lockedOrder->carrier_shipping_fee !== (float) $payload['fee']) {
                    $updates['carrier_shipping_fee'] = (float) $payload['fee'];
                }

                $effectiveStatus = $updates['status'] ?? $oldStatus;
                if ($effectiveStatus === 'completed' && $lockedOrder->payment_method === 'cod' && $oldPaymentStatus !== 'paid' && $this->orderStateTransitionService->canTransitionPaymentStatus($oldPaymentStatus, 'paid')) {
                    $updates['payment_status'] = 'paid';
                }

                $lockedOrder->update($updates);
                $paymentChanged = $oldPaymentStatus !== $lockedOrder->payment_status;
                if (($statusChanged && $lockedOrder->status === 'cancelled') || ($shippingChanged && $lockedOrder->shipping_status === 'returned')) {
                    $this->orderStockService->restore($lockedOrder, 'Hoàn kho theo trạng thái trả/hủy từ GHTK');
                }
                $this->paymentTransactionService->syncInitialTransaction($lockedOrder);

                if ($statusChanged || $paymentChanged || $shippingChanged) {
                    OrderStatusHistory::query()->create([
                        'order_id' => $lockedOrder->id,
                        'from_status' => $oldStatus,
                        'to_status' => $lockedOrder->status,
                        'from_payment_status' => $oldPaymentStatus,
                        'to_payment_status' => $lockedOrder->payment_status,
                        'note' => 'Cập nhật GHTK: '.($lockedOrder->shipping_status ?: 'not_shipped').(! empty($payload['reason']) ? ' - '.$payload['reason'] : ''),
                        'created_at' => now(),
                    ]);
                }

                return ['order' => $lockedOrder->fresh(), 'duplicate' => false, 'status_changed' => $statusChanged];
            }, 3);
        } catch (QueryException $exception) {
            $duplicateEvent = in_array((string) $exception->getCode(), ['23000', '23505'], true)
                && (str_contains(strtolower($exception->getMessage()), 'unique') || (int) ($exception->errorInfo[1] ?? 0) === 1062);
            if (! $duplicateEvent) {
                Log::error('Failed to persist GHTK webhook event.', [
                    'order_number' => $order->order_number,
                    'message' => $exception->getMessage(),
                ]);

                return response()->json(['success' => false, 'message' => 'Unable to process webhook.'], 500);
            }

            Log::info('Duplicate GHTK webhook ignored.', ['order_number' => $order->order_number]);
            $result = ['order' => $order->fresh(), 'duplicate' => true, 'status_changed' => false];
        } catch (\Throwable $exception) {
            Log::error('Failed to process GHTK webhook.', ['order_number' => $order->order_number, 'message' => $exception->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Unable to process webhook.'], 500);
        }

        if ($result['status_changed']) {
            SendOrderStatusEmail::dispatch($result['order']->id, $result['order']->customer_email)->afterCommit();
        }

        return response()->json([
            'success' => true,
            'duplicate' => $result['duplicate'],
            'order_status' => $result['order']->status,
            'payment_status' => $result['order']->payment_status,
            'shipping_status' => $result['order']->shipping_status,
        ]);
    }

    private function orderStatusForGhtk(?int $statusId, string $currentStatus): string
    {
        return match ($statusId) {
            -1 => 'cancelled',
            1, 2 => 'pending',
            3, 4, 8, 10, 12 => 'processing',
            7 => $currentStatus,
            5, 6 => 'completed',
            11, 20, 21 => $currentStatus === 'completed' ? 'completed' : 'cancelled',
            default => $currentStatus,
        };
    }
}
