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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly OrderStockService $orderStockService,
        private readonly OrderStateTransitionService $orderStateTransitionService,
        private readonly PaymentTransactionService $paymentTransactionService,
    ) {}

    /**
     * Handle Giao Hàng Tiết Kiệm (GHTK) webhook callbacks.
     */
    public function handleGHTK(Request $request)
    {
        // Get shipping settings
        $ghtk = ShippingPartner::query()
            ->where('partner_code', 'DTGH000012')
            ->first();

        $configuredToken = $ghtk ? data_get($ghtk->settings, 'webhook_token') : null;
        // Configurations created before the explicit switch retain their
        // webhook behavior. New configurations must opt in to realtime sync.
        $realtimeTrackingEnabled = $ghtk
            ? (bool) data_get($ghtk->settings, 'realtime_tracking_enabled', ! empty($configuredToken))
            : false;

        $providedToken = (string) $request->query('token', '');
        if (! $realtimeTrackingEnabled || ! $configuredToken || ! hash_equals((string) $configuredToken, $providedToken)) {
            Log::warning('GHTK Webhook rejected due to invalid credentials.');

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // Get webhook parameters
        // GHTK webhook fields: partner_id, label_id, status_id, fee, reason
        $partnerId = $request->input('partner_id'); // our order number
        $labelId = $request->input('label_id'); // GHTK tracking number
        $statusId = $request->input('status_id');
        $fee = $request->input('fee');

        if (empty($partnerId)) {
            return response()->json([
                'success' => false,
                'message' => 'partner_id is required.',
            ], 422);
        }

        Log::info('GHTK Webhook accepted.', [
            'partner_id' => $partnerId,
            'label_id' => $labelId,
            'status_id' => $statusId,
        ]);

        // Find the order
        $order = Order::query()->where('order_number', $partnerId)->first();

        if (! $order) {
            Log::warning("GHTK Webhook order not found. partner_id: {$partnerId}");

            return response()->json([
                'success' => false,
                'message' => "Order not found: {$partnerId}",
            ], 404);
        }

        $oldStatus = $order->status;
        $oldPaymentStatus = $order->payment_status;

        // Map status_id to order statuses
        $newStatus = $oldStatus;
        if (! is_null($statusId)) {
            $statusId = (int) $statusId;
            switch ($statusId) {
                case -1: // Hủy đơn hàng
                case 7:  // Không lấy được hàng
                case 9:  // Không giao được hàng
                case 11: // Đã đối soát công nợ trả hàng
                case 13: // Đơn hàng bồi hoàn
                case 20: // Đang trả hàng
                case 21: // Đã trả hàng xong
                    $newStatus = 'cancelled';
                    break;

                case 1:  // Chưa tiếp nhận
                case 2:  // Đã tiếp nhận
                    $newStatus = 'pending';
                    break;

                case 3:  // Đã lấy hàng / Đã nhập kho
                case 4:  // Đang giao hàng
                case 8:  // Hoãn lấy hàng
                case 10: // Delay giao hàng
                case 12: // Đang lấy hàng
                    $newStatus = 'processing';
                    break;

                case 5:  // Đã giao hàng / Chưa đối soát
                case 6:  // Đã đối soát
                    $newStatus = 'completed';
                    break;
            }
        }

        // Update fields
        $updates = [];

        if ($newStatus !== $oldStatus) {
            $updates['status'] = $newStatus;
        }

        if ($labelId && $order->tracking_number !== $labelId) {
            $updates['tracking_number'] = $labelId;
        }

        // Update fee if provided and is greater than 0
        if (! is_null($fee) && (float) $fee > 0 && (float) $order->shipping_fee != (float) $fee) {
            $updates['shipping_fee'] = (float) $fee;
        }

        // If completed and payment is COD, set to paid
        if ($newStatus === 'completed' && $order->payment_method === 'cod' && $order->payment_status !== 'paid') {
            $updates['payment_status'] = 'paid';
        }

        // Update GHTK as shipping carrier if not already set
        if ($order->shipping_carrier !== 'ghtk') {
            $updates['shipping_carrier'] = 'ghtk';
        }

        if (! empty($updates)) {
            $order = DB::transaction(function () use ($order, $updates): Order {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                $previousStatus = $lockedOrder->status;
                $previousPaymentStatus = $lockedOrder->payment_status;

                $nextStatus = $updates['status'] ?? $previousStatus;
                $nextPaymentStatus = $updates['payment_status'] ?? $previousPaymentStatus;
                if (! $this->orderStateTransitionService->canTransitionOrderStatus($previousStatus, $nextStatus)) {
                    Log::warning('Ignoring stale GHTK order status.', [
                        'order_number' => $lockedOrder->order_number,
                        'from_status' => $previousStatus,
                        'to_status' => $nextStatus,
                    ]);
                    unset($updates['status']);
                }
                if (! $this->orderStateTransitionService->canTransitionPaymentStatus($previousPaymentStatus, $nextPaymentStatus)) {
                    Log::warning('Ignoring invalid GHTK payment status.', [
                        'order_number' => $lockedOrder->order_number,
                        'from_payment_status' => $previousPaymentStatus,
                        'to_payment_status' => $nextPaymentStatus,
                    ]);
                    unset($updates['payment_status']);
                }

                $lockedOrder->update($updates);

                if ($lockedOrder->status === 'cancelled' && $previousStatus !== 'cancelled') {
                    $this->orderStockService->restore($lockedOrder, 'Hoàn kho theo trạng thái trả/hủy từ GHTK');
                }
                $this->paymentTransactionService->syncInitialTransaction($lockedOrder);

                if ($previousStatus !== $lockedOrder->status || $previousPaymentStatus !== $lockedOrder->payment_status) {
                    OrderStatusHistory::query()->create([
                        'order_id' => $lockedOrder->id,
                        'from_status' => $previousStatus,
                        'to_status' => $lockedOrder->status,
                        'from_payment_status' => $previousPaymentStatus,
                        'to_payment_status' => $lockedOrder->payment_status,
                        'note' => 'Cập nhật tự động từ GHTK',
                        'created_at' => now(),
                    ]);
                }

                return $lockedOrder->fresh();
            });

            // Send email if status changed
            if (isset($updates['status']) && $oldStatus !== $updates['status']) {
                SendOrderStatusEmail::dispatch($order->id, $order->customer_email)->afterCommit();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully.',
            'order_status' => $order->status,
            'payment_status' => $order->payment_status,
        ]);
    }
}
