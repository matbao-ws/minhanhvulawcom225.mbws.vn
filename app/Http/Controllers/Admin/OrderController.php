<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly \App\Services\ShippingService $shippingService)
    {
    }

    /**
     * Display a listing of orders.
     */
    public function index(Request $request)
    {
        $query = Order::query()->latest();

        // Search query filter
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%")
                    ->orWhere('customer_email', 'like', "%{$q}%");
            });
        }

        // Order Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Payment Status filter
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        $orders = $query->paginate(10)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    /**
     * Display details of a specific order.
     */
    public function show(string $locale, Order $order)
    {
        $order->load(['items.product', 'items.variant']);
        
        $shippingSettings = $this->shippingService->getSettings();
        $isGhtkEnabled = (bool) data_get($shippingSettings, 'ghtk.enabled', false);

        return view('admin.orders.show', compact('order', 'isGhtkEnabled'));
    }

    /**
     * Update order and payment status.
     */
    public function updateStatus(Request $request, string $locale, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
            'payment_status' => 'required|in:pending,paid,failed',
        ]);

        $oldStatus = $order->status;
        $order->update($validated);

        if ($oldStatus !== $order->status) {
            $order->load('items');
            \Illuminate\Support\Facades\Mail::to($order->customer_email)
                ->send(new \App\Mail\OrderStatusMail($order));
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', __('admin.orders.updated_status_success'));
    }

    /**
     * Push order to shipping carrier.
     */
    public function pushShipping(Request $request, string $locale, Order $order)
    {
        $validated = $request->validate([
            'carrier' => 'required|in:ghtk',
            'weight' => 'required|integer|min:1',
            'province' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'ward' => 'required|string|max:255',
        ]);

        $result = $this->shippingService->pushToGHTK($order, $validated);

        if ($result['success']) {
            $order->update([
                'shipping_carrier' => $validated['carrier'],
                'shipping_fee' => $result['fee'],
                'tracking_number' => $result['tracking_number'],
                'status' => 'processing',
            ]);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'tracking_number' => $result['tracking_number'],
                'fee' => $result['fee']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 422);
    }
}
