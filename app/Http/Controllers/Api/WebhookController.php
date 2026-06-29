<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProjectSetting;
use App\Mail\OrderStatusMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WebhookController extends Controller
{
    /**
     * Handle Giao Hàng Tiết Kiệm (GHTK) webhook callbacks.
     */
    public function handleGHTK(Request $request)
    {
        Log::info('GHTK Webhook payload received: ', $request->all());

        // Get shipping settings
        $ghtk = \App\Models\ShippingPartner::query()
            ->where('partner_code', 'DTGH000012')
            ->first();

        $configuredToken = $ghtk ? data_get($ghtk->settings, 'webhook_token') : null;

        // Verify token if configured
        if ($configuredToken && $request->query('token') !== $configuredToken) {
            Log::warning('GHTK Webhook unauthorized. Invalid token: ' . $request->query('token'));
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized token.'
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
                'message' => 'partner_id is required.'
            ], 422);
        }

        // Find the order
        $order = Order::query()->where('order_number', $partnerId)->first();

        if (!$order) {
            Log::warning("GHTK Webhook order not found. partner_id: {$partnerId}");
            return response()->json([
                'success' => false,
                'message' => "Order not found: {$partnerId}"
            ], 404);
        }

        $oldStatus = $order->status;
        $oldPaymentStatus = $order->payment_status;

        // Map status_id to order statuses
        $newStatus = $oldStatus;
        if (!is_null($statusId)) {
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
        if (!is_null($fee) && (float) $fee > 0 && (float) $order->shipping_fee != (float) $fee) {
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

        if (!empty($updates)) {
            $order->update($updates);

            // Send email if status changed
            if (isset($updates['status']) && $oldStatus !== $updates['status']) {
                try {
                    $order->load('items');
                    Mail::to($order->customer_email)->send(new OrderStatusMail($order));
                } catch (\Exception $e) {
                    Log::error("Failed to send order status update email for Order {$order->id}: " . $e->getMessage());
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully.',
            'order_status' => $order->status,
            'payment_status' => $order->payment_status
        ]);
    }
}
