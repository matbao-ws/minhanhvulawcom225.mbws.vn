<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderStatusEmail;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderRefundItem;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ActivityLogger;
use App\Services\OrderStateTransitionService;
use App\Services\OrderStockService;
use App\Services\PaymentTransactionService;
use App\Services\PromotionService;
use App\Services\LocalizedContent;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        private readonly ShippingService $shippingService,
        private readonly OrderStockService $orderStockService,
        private readonly OrderStateTransitionService $orderStateTransitionService,
        private readonly PaymentTransactionService $paymentTransactionService,
        private readonly PromotionService $promotionService,
        private readonly LocalizedContent $localizedContent,
    ) {}

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

        if ($request->filled('shipping_status')) {
            $query->where('shipping_status', $request->input('shipping_status'));
        }

        $orders = $query->paginate(10)->withQueryString();
        $shippingStatuses = Order::shippingStatusKeys();

        return view('admin.orders.index', compact('orders', 'shippingStatuses'));
    }

    /**
     * Display details of a specific order.
     */
    public function show(string $locale, Order $order)
    {
        $order->load(['items.product', 'items.variant', 'historyEntries.changedBy', 'refunds.items.orderItem', 'refunds.createdBy']);

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
            'payment_status' => 'required|in:pending,paid,failed,refunded,partially_refunded',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $transition = DB::transaction(function () use ($order, $validated): array {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                $oldStatus = $lockedOrder->status;
                $oldPaymentStatus = $lockedOrder->payment_status;
                $statusChanged = $oldStatus !== $validated['status'];
                $paymentChanged = $oldPaymentStatus !== $validated['payment_status'];

                if (! $statusChanged && ! $paymentChanged) {
                    return ['status_changed' => false];
                }

                if ($paymentChanged && in_array($validated['payment_status'], ['refunded', 'partially_refunded'], true)) {
                    throw new \DomainException('Hoàn tiền phải được thực hiện qua chức năng hoàn tiền để đối soát số tiền và tồn kho.');
                }

                $this->orderStateTransitionService->assertCanTransition(
                    $oldStatus,
                    $validated['status'],
                    $oldPaymentStatus,
                    $validated['payment_status'],
                );
                $lockedOrder->update([
                    'status' => $validated['status'],
                    'payment_status' => $validated['payment_status'],
                ]);

                if ($lockedOrder->status === 'cancelled' && $oldStatus !== 'cancelled') {
                    $this->orderStockService->restore($lockedOrder);
                }
                $this->paymentTransactionService->syncInitialTransaction($lockedOrder);

                OrderStatusHistory::query()->create([
                    'order_id' => $lockedOrder->id,
                    'from_status' => $oldStatus,
                    'to_status' => $lockedOrder->status,
                    'from_payment_status' => $oldPaymentStatus,
                    'to_payment_status' => $lockedOrder->payment_status,
                    'note' => $validated['note'] ?? null,
                    'changed_by' => auth()->id(),
                    'created_at' => now(),
                ]);

                ActivityLogger::log('status_changed', $lockedOrder, "Cập nhật trạng thái đơn hàng {$lockedOrder->order_number}", [
                    'old' => ['status' => $oldStatus, 'payment_status' => $oldPaymentStatus],
                    'new' => ['status' => $lockedOrder->status, 'payment_status' => $lockedOrder->payment_status],
                ]);

                return ['status_changed' => $statusChanged];
            });
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['status' => $exception->getMessage()]);
        }

        if ($transition['status_changed']) {
            $order->refresh();
            SendOrderStatusEmail::dispatch($order->id, $order->customer_email)->afterCommit();
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', __('admin.orders.updated_status_success'));
    }

    public function create()
    {
        return view('admin.orders.create', [
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'brands' => Brand::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'products' => Product::query()->where('is_active', true)->with(['variants' => fn ($query) => $query->where('is_active', true)])->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'payment_method' => 'required|string|max:100',
            'shipping_fee' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if (! PaymentMethod::query()->where('method_code', $validated['payment_method'])->where('status', 'active')->exists()) {
            return back()->withInput()->withErrors(['payment_method' => 'Phương thức thanh toán chưa được kích hoạt.']);
        }

        try {
            $order = DB::transaction(function () use ($validated) {
                $subtotal = 0.0;
                $promotionDiscount = 0.0;
                $items = [];

                foreach ($validated['items'] as $input) {
                    $product = Product::query()->lockForUpdate()->find($input['product_id']);
                    if (! $product || ! $product->is_active) {
                        throw new \DomainException('Sản phẩm không còn kinh doanh.');
                    }

                    $variant = null;
                    if (! empty($input['variant_id'])) {
                        $variant = ProductVariant::query()->whereKey($input['variant_id'])->where('product_id', $product->id)->where('is_active', true)->lockForUpdate()->first();
                        if (! $variant) {
                            throw new \DomainException("Biến thể của {$product->name} không hợp lệ.");
                        }
                    }

                    if ($product->usesVariantInventory() && ! $variant) {
                        throw new \DomainException("Vui lòng chọn SKU biến thể cho {$product->name}.");
                    }

                    $quantity = (int) $input['quantity'];
                    if ($product->manage_stock && (! $variant || ! $product->usesVariantInventory()) && $product->stock_quantity < $quantity) {
                        throw new \DomainException("{$product->name} không đủ tồn kho.");
                    }

                    $price = $variant?->price !== null ? (float) $variant->price : (float) $product->price;
                    $quote = $this->promotionService->quote($product, $variant, $price, $quantity);
                    $subtotal += $price * $quantity;
                    $promotionDiscount += $quote['discount'];
                    $items[] = ['quote' => $quote, 'data' => [
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
                    ]];
                }

                $manualDiscount = (float) ($validated['discount'] ?? 0);
                $discountableSubtotal = max(0, $subtotal - $promotionDiscount);
                if ($manualDiscount > $discountableSubtotal) {
                    throw new \DomainException('Giảm giá thủ công không thể lớn hơn tiền hàng sau khuyến mãi.');
                }
                $discount = $promotionDiscount + $manualDiscount;
                $shippingFee = (float) ($validated['shipping_fee'] ?? 0);
                do {
                    $orderNumber = 'ORD-'.strtoupper(Str::random(10));
                } while (Order::query()->where('order_number', $orderNumber)->exists());

                $order = Order::query()->create([
                    'order_number' => $orderNumber,
                    'locale' => app()->getLocale(),
                    'customer_name' => $validated['customer_name'],
                    'customer_email' => $validated['customer_email'],
                    'customer_phone' => $validated['customer_phone'],
                    'shipping_address' => $validated['shipping_address'],
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => 'pending',
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'promotion_discount' => $promotionDiscount,
                    'shipping_fee' => $shippingFee,
                    'grand_total' => max(0, $subtotal - $promotionDiscount - $manualDiscount) + $shippingFee,
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($items as $item) {
                    $orderItem = $order->items()->create($item['data']);
                    $this->promotionService->reserve($item['quote'], (int) $orderItem->quantity);
                    $this->orderStockService->deduct($order, $orderItem, 'Xuất kho cho đơn tạo thủ công');
                }
                $this->paymentTransactionService->createPending($order, 'manual_order');
                OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'to_status' => 'pending',
                    'to_payment_status' => 'pending',
                    'note' => 'Tạo đơn thủ công',
                    'changed_by' => auth()->id(),
                    'created_at' => now(),
                ]);
                ActivityLogger::log('created', $order, "Tạo đơn hàng thủ công {$order->order_number}");

                return $order;
            }, 3);
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['items' => $exception->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('success', 'Đã tạo đơn hàng thành công.');
    }

    public function refund(Request $request, string $locale, Order $order)
    {
        $validated = $request->validate([
            'type' => 'required|in:full,partial',
            'reason' => 'nullable|string|max:1000',
            'items' => 'required_if:type,partial|array',
            'items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($validated, $order): void {
                $lockedOrder = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
                if (! in_array($lockedOrder->payment_status, ['paid', 'partially_refunded'], true)) {
                    throw new \DomainException('Chỉ đơn đã thanh toán mới có thể hoàn tiền.');
                }
                $alreadyRefunded = (float) $lockedOrder->refunds()->sum('amount');
                if ($alreadyRefunded >= (float) $lockedOrder->grand_total) {
                    throw new \DomainException('Đơn hàng đã được hoàn tiền toàn bộ.');
                }

                $itemsById = $lockedOrder->items->keyBy('id');
                $quantitiesRefunded = OrderRefundItem::query()
                    ->whereIn('order_item_id', $itemsById->keys())
                    ->selectRaw('order_item_id, SUM(quantity) as quantity')
                    ->groupBy('order_item_id')
                    ->pluck('quantity', 'order_item_id');

                if ($validated['type'] === 'full') {
                    if ($alreadyRefunded > 0) {
                        throw new \DomainException('Chỉ có thể hoàn toàn phần khi đơn chưa có khoản hoàn tiền trước đó.');
                    }
                    $refundItems = $lockedOrder->items->map(fn ($item) => [
                        'order_item_id' => $item->id,
                        'quantity' => $item->quantity,
                        'amount' => (float) $item->total,
                    ])->all();
                    $amount = (float) $lockedOrder->grand_total;
                } else {
                    $refundItems = [];
                    $amount = 0.0;
                    $requestedQuantities = [];
                    foreach ($validated['items'] as $input) {
                        $item = $itemsById->get($input['order_item_id']);
                        if (! $item) {
                            throw new \DomainException('Sản phẩm hoàn tiền không thuộc đơn hàng này.');
                        }
                        $quantity = (int) $input['quantity'];
                        $remainingQuantity = $item->quantity
                            - (int) ($quantitiesRefunded[$item->id] ?? 0)
                            - (int) ($requestedQuantities[$item->id] ?? 0);
                        if ($quantity > $remainingQuantity) {
                            throw new \DomainException("Số lượng hoàn của {$item->product_name} vượt quá số lượng có thể hoàn.");
                        }
                        $lineAmount = (float) $item->price * $quantity;
                        $refundItems[] = ['order_item_id' => $item->id, 'quantity' => $quantity, 'amount' => $lineAmount];
                        $requestedQuantities[$item->id] = (int) ($requestedQuantities[$item->id] ?? 0) + $quantity;
                        $amount += $lineAmount;
                    }
                    if (! $refundItems || $amount <= 0 || $alreadyRefunded + $amount > (float) $lockedOrder->grand_total) {
                        throw new \DomainException('Số tiền hoàn không hợp lệ.');
                    }
                }

                $refund = $lockedOrder->refunds()->create([
                    'amount' => $amount,
                    'type' => $validated['type'],
                    'reason' => $validated['reason'] ?? null,
                    'created_by' => auth()->id(),
                ]);
                $refund->items()->createMany($refundItems);

                $totalRefunded = $alreadyRefunded + $amount;
                $newPaymentStatus = $totalRefunded >= (float) $lockedOrder->grand_total ? 'refunded' : 'partially_refunded';
                $oldPaymentStatus = $lockedOrder->payment_status;
                $lockedOrder->update(['payment_status' => $newPaymentStatus]);
                $this->paymentTransactionService->syncInitialTransaction($lockedOrder);
                if ($lockedOrder->status !== 'cancelled') {
                    $this->orderStockService->restorePartial(
                        $lockedOrder,
                        $refundItems,
                        $refund->id,
                        'Hoàn kho theo hoàn tiền đơn hàng',
                    );
                }
                OrderStatusHistory::query()->create([
                    'order_id' => $lockedOrder->id,
                    'from_status' => $lockedOrder->status,
                    'to_status' => $lockedOrder->status,
                    'from_payment_status' => $oldPaymentStatus,
                    'to_payment_status' => $newPaymentStatus,
                    'note' => $validated['reason'] ?? 'Hoàn tiền',
                    'changed_by' => auth()->id(),
                    'created_at' => now(),
                ]);
                ActivityLogger::log('refunded', $lockedOrder, "Ghi nhận hoàn tiền đơn hàng {$lockedOrder->order_number}", [
                    'amount' => $amount,
                    'type' => $validated['type'],
                ]);
            }, 3);
        } catch (\DomainException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('success', 'Đã ghi nhận hoàn tiền.');
    }

    /**
     * Push order to shipping carrier.
     */
    public function pushShipping(Request $request, string $locale, Order $order)
    {
        $validated = $request->validate([
            'carrier' => 'required|in:ghtk',
            'weight' => 'required|integer|min:1|max:50000',
            'province' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'ward' => 'required|string|max:255',
        ]);

        try {
            $claimedOrder = DB::transaction(function () use ($order): Order {
                $lockedOrder = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
                if ($lockedOrder->tracking_number || $lockedOrder->shipping_status === 'shipping_pending') {
                    throw new \DomainException('Đơn hàng đang hoặc đã được đẩy sang đơn vị vận chuyển.');
                }
                if (! Order::canTransitionShippingStatus($lockedOrder->shipping_status ?: 'not_shipped', 'shipping_pending')) {
                    throw new \DomainException('Trạng thái giao hàng hiện tại không thể đẩy đơn.');
                }
                if (! $this->orderStateTransitionService->canTransitionOrderStatus($lockedOrder->status, 'processing')) {
                    throw new \DomainException('Trạng thái đơn hàng hiện tại không thể đẩy sang giao hàng.');
                }
                $lockedOrder->update([
                    'shipping_status' => 'shipping_pending',
                    'shipping_status_updated_at' => now(),
                ]);

                return $lockedOrder->fresh(['items']);
            }, 3);
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        $result = $this->shippingService->pushToGHTK($claimedOrder, $validated);

        if ($result['success']) {
            try {
                $order = DB::transaction(function () use ($order, $validated, $result): Order {
                    $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                    $oldStatus = $lockedOrder->status;
                    $this->orderStateTransitionService->assertCanTransition(
                        $oldStatus,
                        'processing',
                        $lockedOrder->payment_status,
                        $lockedOrder->payment_status,
                    );
                    $lockedOrder->update([
                        'shipping_carrier' => $validated['carrier'],
                        'carrier_shipping_fee' => $result['fee'],
                        'tracking_number' => $result['tracking_number'],
                        'shipping_status' => 'shipping_created',
                        'shipping_status_updated_at' => now(),
                        'status' => 'processing',
                    ]);

                    if ($oldStatus !== 'processing') {
                        OrderStatusHistory::query()->create([
                            'order_id' => $lockedOrder->id,
                            'from_status' => $oldStatus,
                            'to_status' => 'processing',
                            'from_payment_status' => $lockedOrder->payment_status,
                            'to_payment_status' => $lockedOrder->payment_status,
                            'note' => 'Đẩy đơn sang GHTK',
                            'changed_by' => auth()->id(),
                            'created_at' => now(),
                        ]);
                    }
                    ActivityLogger::log('shipping_pushed', $lockedOrder, "Đẩy đơn hàng {$lockedOrder->order_number} sang GHTK", [
                        'tracking_number' => $result['tracking_number'],
                        'carrier_shipping_fee' => $result['fee'],
                    ]);

                    return $lockedOrder->fresh();
                });
            } catch (\DomainException $exception) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'tracking_number' => $result['tracking_number'],
                'fee' => $result['fee'],
            ]);
        }

        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->shipping_status === 'shipping_pending' && ! $lockedOrder->tracking_number) {
                $lockedOrder->update(['shipping_status' => 'not_shipped', 'shipping_status_updated_at' => now()]);
            }
        });

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 422);
    }
}
