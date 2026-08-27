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
use App\Services\SePayService;
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

    /**
     * Handle an incoming bank transfer pushed by SePay.
     *
     * This is the only thing that may move a SePay order to `paid`. SePay requires a
     * 200 with `{"success": true}` within 30 seconds and retries otherwise, so every
     * outcome we have finished reasoning about — a transfer that is not ours, an
     * unmatched code, a duplicate, an underpayment — answers success and is logged
     * rather than left to be redelivered forever.
     */
    public function handleSepay(Request $request, SePayService $sepay)
    {
        $method = $sepay->method();

        if ($method?->status !== 'active' || ! $sepay->isConfigured($method)) {
            Log::warning('SePay webhook received while the gateway is inactive or unconfigured.');

            return response()->json(['success' => false, 'message' => 'Gateway unavailable.'], 503);
        }

        if (! $sepay->verifyWebhookRequest($request, $method)) {
            Log::warning('SePay webhook rejected due to invalid credentials.');

            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $payload = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'gateway' => ['nullable', 'string', 'max:100'],
            'transactionDate' => ['nullable', 'string', 'max:40'],
            'accountNumber' => ['nullable', 'string', 'max:64'],
            'subAccount' => ['nullable', 'string', 'max:64'],
            'code' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:2000'],
            'transferType' => ['required', 'string', 'in:in,out'],
            'description' => ['nullable', 'string', 'max:2000'],
            'transferAmount' => ['required', 'numeric', 'min:0', 'max:1000000000000'],
            'accumulated' => ['nullable', 'numeric'],
            'referenceCode' => ['nullable', 'string', 'max:255'],
        ]);

        // Outgoing money is never a customer payment.
        if ($payload['transferType'] !== 'in') {
            return response()->json(['success' => true]);
        }

        if (! $sepay->matchesConfiguredAccount($payload, $method)) {
            Log::warning('SePay webhook ignored: transfer landed on an unconfigured account.', [
                'sepay_transaction_id' => $payload['id'],
            ]);

            return response()->json(['success' => true]);
        }

        $order = $sepay->resolveOrder($payload, $method);
        if (! $order) {
            // Expected for any transfer into the account that is not an order payment.
            Log::info('SePay webhook has no matching order.', ['sepay_transaction_id' => $payload['id']]);

            return response()->json(['success' => true]);
        }

        try {
            $result = DB::transaction(function () use ($order, $payload): array {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                $expectedAmount = (int) round((float) $lockedOrder->grand_total);
                $receivedAmount = (int) round((float) $payload['transferAmount']);
                // Bank transfers get rounded up by customers far more often than they
                // get shorted, so the order is settled on "at least the total" and the
                // exact figure that arrived is kept on the transaction.
                $sufficient = $receivedAmount >= $expectedAmount;

                $transaction = $this->paymentTransactionService->recordSepayWebhook(
                    $lockedOrder,
                    $payload,
                    $sufficient ? 'paid' : 'pending',
                    $sufficient ? 'paid' : 'underpaid',
                );

                if (! $transaction->wasRecentlyCreated) {
                    return ['outcome' => 'duplicate', 'order' => $lockedOrder, 'status_changed' => false];
                }

                if (! $sufficient) {
                    return ['outcome' => 'underpaid', 'order' => $lockedOrder, 'status_changed' => false];
                }

                if ($lockedOrder->payment_status === 'paid') {
                    return ['outcome' => 'already_paid', 'order' => $lockedOrder, 'status_changed' => false];
                }

                if (! in_array($lockedOrder->payment_status, ['pending', 'failed'], true)) {
                    return ['outcome' => 'not_updatable', 'order' => $lockedOrder, 'status_changed' => false];
                }

                if ($lockedOrder->status === 'cancelled') {
                    return ['outcome' => 'order_cancelled', 'order' => $lockedOrder, 'status_changed' => false];
                }

                $oldStatus = $lockedOrder->status;
                $oldPaymentStatus = $lockedOrder->payment_status;
                $newStatus = $oldStatus === 'pending' ? 'processing' : $oldStatus;

                $this->orderStateTransitionService->assertCanTransition($oldStatus, $newStatus, $oldPaymentStatus, 'paid');
                $lockedOrder->update(['status' => $newStatus, 'payment_status' => 'paid']);
                $this->paymentTransactionService->syncInitialTransaction($lockedOrder);

                OrderStatusHistory::query()->create([
                    'order_id' => $lockedOrder->id,
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'from_payment_status' => $oldPaymentStatus,
                    'to_payment_status' => 'paid',
                    'note' => 'Cập nhật thanh toán từ webhook SePay',
                    'created_at' => now(),
                ]);

                return ['outcome' => 'paid', 'order' => $lockedOrder->fresh(), 'status_changed' => true];
            }, 3);
        } catch (\Throwable $exception) {
            // Answer with an error so SePay retries: the transfer is real and unrecorded.
            Log::error('Failed to process SePay webhook.', [
                'order_number' => $order->order_number,
                'sepay_transaction_id' => $payload['id'],
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Unable to process webhook.'], 500);
        }

        if ($result['outcome'] !== 'paid') {
            Log::warning('SePay webhook did not settle the order.', [
                'order_number' => $order->order_number,
                'sepay_transaction_id' => $payload['id'],
                'outcome' => $result['outcome'],
            ]);
        }

        if ($result['status_changed']) {
            SendOrderStatusEmail::dispatch($result['order']->id, $result['order']->customer_email)->afterCommit();
        }

        return response()->json(['success' => true]);
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
