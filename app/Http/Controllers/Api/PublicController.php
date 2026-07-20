<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicOrderResource;
use App\Http\Resources\PublicProductResource;
use App\Http\Resources\PublicReviewResource;
use App\Jobs\SendOrderStatusEmail;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProjectSetting;
use App\Models\Review;
use App\Models\Voucher;
use App\Services\Catalog\ProductVariantResolver;
use App\Services\OrderStockService;
use App\Services\PaymentTransactionService;
use App\Services\PromotionService;
use App\Services\ShippingService;
use App\Services\VNPAYService;
use App\Support\ApiResponse;
use App\Support\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    public function __construct(
        private readonly OrderStockService $orderStockService,
        private readonly PaymentTransactionService $paymentTransactionService,
        private readonly ProductVariantResolver $variantResolver,
        private readonly PromotionService $promotionService,
    ) {}

    /**
     * App health check.
     */
    public function health()
    {
        return ApiResponse::success([
            'app' => __('api.app_name'),
        ]);
    }

    /**
     * Display Swagger API documentation page.
     */
    public function docs()
    {
        return view('api.docs');
    }

    /**
     * Get system settings.
     */
    public function settings()
    {
        $settings = ProjectSetting::query()
            ->whereIn('setting_key', [
                'shop_name',
                'logo_url',
                'favicon_url',
                'contact',
                'theme',
                'seo',
                'social_links',
            ])
            ->pluck('setting_value', 'setting_key')
            ->all();

        return ApiResponse::success($settings);
    }

    /**
     * Get category tree list.
     */
    public function categories()
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success($categories);
    }

    /**
     * Get brand list.
     */
    public function brands()
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success($brands);
    }

    /**
     * Get filterable products list.
     */
    public function products(Request $request)
    {
        $query = Product::query()->where('is_active', true);

        // Filter by Category
        if ($request->filled('category')) {
            $cat = $request->input('category');
            $query->whereHas('category', function ($q) use ($cat) {
                $q->where('id', $cat)->orWhere('slug', $cat);
            });
        }

        // Filter by Brand
        if ($request->filled('brand')) {
            $brand = $request->input('brand');
            $query->whereHas('brand', function ($q) use ($brand) {
                $q->where('id', $brand)->orWhere('slug', $brand);
            });
        }

        // Search by keyword
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            });
        }

        // Filter by Price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        // Sort results
        $sortBy = $request->input('sort_by', 'latest');
        if ($sortBy === 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($sortBy === 'price_desc') {
            $query->orderBy('price', 'desc');
        } else {
            $query->latest();
        }

        $products = $query->paginate(12)->withQueryString();

        return ApiResponse::success(PublicProductResource::collection($products->items()), 'Lấy danh sách sản phẩm thành công.', [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
        ]);
    }

    /**
     * Get single product details.
     */
    public function productDetail($idOrSlug)
    {
        $product = Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($idOrSlug) {
                $q->where('id', $idOrSlug)->orWhere('slug', $idOrSlug);
            })
            ->with(['category', 'brand', 'optionGroups.values' => function ($q) {
                $q->where('is_active', true);
            }, 'variants.optionValues.optionGroup', 'variants' => function ($q) {
                $q->where('is_active', true);
            }, 'reviews' => function ($q) {
                $q->where('is_visible', true)->latest();
            }])
            ->first();

        if (! $product) {
            return ApiResponse::error('Sản phẩm không tồn tại.', 404);
        }

        return ApiResponse::success(new PublicProductResource($product));
    }

    /**
     * Validate and apply voucher discount to cart.
     */
    public function applyVoucher(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $code = strtoupper($request->input('code'));
        $subtotal = (float) $request->input('subtotal');

        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            return ApiResponse::error('Mã giảm giá không tồn tại.', 422);
        }

        if (! $voucher->is_active) {
            return ApiResponse::error('Mã giảm giá đã bị khóa.', 422);
        }

        $now = now();
        if ($voucher->start_date && $voucher->start_date->isAfter($now)) {
            return ApiResponse::error('Chương trình giảm giá chưa bắt đầu.', 422);
        }
        if ($voucher->end_date && $voucher->end_date->isBefore($now)) {
            return ApiResponse::error('Mã giảm giá đã hết hạn sử dụng.', 422);
        }

        if ($voucher->quantity !== null && $voucher->used_count >= $voucher->quantity) {
            return ApiResponse::error('Mã giảm giá đã hết lượt sử dụng.', 422);
        }

        if ($subtotal < (float) $voucher->min_order_amount) {
            return ApiResponse::error('Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã này.', 422);
        }

        $discount = $voucher->calculateDiscount($subtotal);

        return ApiResponse::success([
            'code' => $voucher->code,
            'type' => $voucher->type,
            'value' => $voucher->value,
            'discount_amount' => $discount,
        ], 'Áp dụng mã giảm giá thành công.');
    }

    /**
     * Create checkout order.
     */
    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'payment_method' => 'required|string|max:100',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.option_value_ids' => 'nullable|array|max:8',
            'items.*.option_value_ids.*' => 'integer|distinct|exists:product_option_values,id',
            'items.*.quantity' => 'required|integer|min:1',
            'voucher_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $paymentMethod = PaymentMethod::query()
            ->where('method_code', $request->input('payment_method'))
            ->where('status', 'active')
            ->first();

        if (! $paymentMethod) {
            return ApiResponse::error('Phương thức thanh toán chưa được kích hoạt hoặc không tồn tại.', 422);
        }

        $shipping = app(ShippingService::class)->getSettings();
        $shippingFee = data_get($shipping, 'flat_rate.enabled')
            ? (float) data_get($shipping, 'flat_rate.fee', 0)
            : 0.0;

        $customerId = $request->user('sanctum')?->id;
        try {
            $order = DB::transaction(function () use ($request, $customerId, $shippingFee) {
                $subtotal = 0.0;
                $promotionDiscount = 0.0;
                $orderItems = [];

                foreach ($request->input('items') as $item) {
                    $product = Product::query()->lockForUpdate()->find($item['product_id']);
                    if (! $product || ! $product->is_active) {
                        throw new \DomainException("Sản phẩm ID {$item['product_id']} không tồn tại hoặc đã ngừng kinh doanh.");
                    }

                    $variant = null;
                    $price = (float) $product->price;
                    if ($product->usesVariantInventory()) {
                        $variant = $this->variantResolver->resolve($product, $item['option_value_ids'] ?? [], true);
                        $price = $variant->price !== null ? (float) $variant->price : $price;
                    } elseif (! empty($item['variant_id'])) {
                        throw new \DomainException('API mới không nhận variant_id trực tiếp. Hãy gửi option_value_ids để chọn SKU.');
                    }

                    $quantity = (int) $item['quantity'];
                    if ($product->manage_stock && ! $product->usesVariantInventory() && $product->stock_quantity < $quantity) {
                        throw new \DomainException("Sản phẩm {$product->name} đã hết hàng hoặc không đủ tồn kho.");
                    }

                    $quote = $this->promotionService->quote($product, $variant, $price, $quantity);
                    $subtotal += $price * $quantity;
                    $promotionDiscount += $quote['discount'];
                    $orderItems[] = [
                        'product' => $product,
                        'quote' => $quote,
                        'data' => [
                            'product_id' => $product->id,
                            'product_variant_id' => $variant?->id,
                            'promotion_id' => $quote['promotion']?->id,
                            'product_name' => $product->name,
                            'variant_name' => $variant?->name,
                            'promotion_name' => $quote['promotion']?->name,
                            'sku' => $variant?->sku ?: $product->sku,
                            'original_price' => $price,
                            'promotion_discount' => $quote['discount'],
                            'price' => $quote['unit_price'],
                            'quantity' => $quantity,
                            'total' => $quote['unit_price'] * $quantity,
                        ],
                    ];
                }

                $voucher = null;
                $voucherDiscount = 0.0;
                $discountableSubtotal = max(0.0, $subtotal - $promotionDiscount);
                if ($request->filled('voucher_code')) {
                    $voucher = Voucher::query()
                        ->where('code', strtoupper($request->input('voucher_code')))
                        ->lockForUpdate()
                        ->first();
                    if (! $voucher || ! $voucher->isValidForOrder($discountableSubtotal)) {
                        throw new \DomainException('Mã giảm giá không hợp lệ cho đơn hàng này.');
                    }
                    $voucherDiscount = $voucher->calculateDiscount($discountableSubtotal);
                }
                $discount = $promotionDiscount + $voucherDiscount;

                do {
                    $orderNumber = 'ORD-'.strtoupper(Str::random(10));
                } while (Order::query()->where('order_number', $orderNumber)->exists());

                $order = Order::query()->create([
                    'order_number' => $orderNumber,
                    'customer_name' => $request->customer_name,
                    'customer_email' => $request->customer_email,
                    'customer_phone' => $request->customer_phone,
                    'shipping_address' => $request->shipping_address,
                    'payment_method' => $request->payment_method,
                    'payment_status' => 'pending',
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'promotion_discount' => $promotionDiscount,
                    'shipping_fee' => $shippingFee,
                    'grand_total' => max(0.0, $subtotal - $promotionDiscount - $voucherDiscount) + $shippingFee,
                    'notes' => $request->notes,
                    'user_id' => $customerId,
                ]);

                foreach ($orderItems as $item) {
                    $orderItem = $order->items()->create($item['data']);
                    $this->promotionService->reserve($item['quote'], (int) $orderItem->quantity);
                    $this->orderStockService->deduct($order, $orderItem, 'Xuất kho từ checkout API');
                }

                $this->paymentTransactionService->createPending($order, 'public_checkout');
                OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'to_status' => 'pending',
                    'to_payment_status' => 'pending',
                    'note' => 'Tạo đơn hàng từ checkout API',
                    'created_at' => now(),
                ]);

                if ($voucher) {
                    $voucher->increment('used_count');
                }

                return $order->load('items');
            }, 3);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('Checkout transaction failed: '.$e->getMessage());

            return ApiResponse::error('Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại.', 500);
        }

        $paymentUrl = null;
        if ($order->payment_method === 'vnpay') {
            $vnpayService = app(VNPAYService::class);
            $paymentUrl = $vnpayService->createPayment($order, $request->input('redirect_url'));

            if (! $paymentUrl) {
                try {
                    DB::transaction(function () use ($order, $request) {
                        $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                        $oldStatus = $lockedOrder->status;
                        $lockedOrder->update(['status' => 'cancelled', 'notes' => $lockedOrder->notes.' (Khởi tạo thanh toán VNPAY thất bại)']);
                        $this->orderStockService->restore($lockedOrder, 'Hoàn kho do khởi tạo thanh toán VNPAY thất bại');
                        OrderStatusHistory::query()->create([
                            'order_id' => $lockedOrder->id,
                            'from_status' => $oldStatus,
                            'to_status' => 'cancelled',
                            'from_payment_status' => $lockedOrder->payment_status,
                            'to_payment_status' => $lockedOrder->payment_status,
                            'note' => 'Khởi tạo thanh toán VNPAY thất bại',
                            'created_at' => now(),
                        ]);
                        if ($request->filled('voucher_code')) {
                            Voucher::query()
                                ->where('code', strtoupper($request->input('voucher_code')))
                                ->where('used_count', '>', 0)
                                ->decrement('used_count');
                        }
                    });
                } catch (\Throwable $ex) {
                    Log::error('Could not roll back failed VNPAY checkout: '.$ex->getMessage());
                }

                return ApiResponse::error('Không thể khởi tạo giao dịch thanh toán VNPAY. Vui lòng cấu hình các trường TMN Code, Hash Secret hoặc thử lại sau.', 422);
            }
        }

        // Notifications are handled by workers so checkout does not wait for SMTP or Zalo.
        SendOrderStatusEmail::dispatch($order->id, $order->customer_email)->afterCommit();
        NotificationHelper::sendNewOrderNotification($order);

        $responseData = (new PublicOrderResource($order))->resolve();
        if ($paymentUrl) {
            $responseData['payment_url'] = $paymentUrl;
        }

        return ApiResponse::success($responseData, 'Đặt hàng thành công.');
    }

    /**
     * Track order status without login.
     */
    public function trackOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string',
            'contact' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $orderNumber = $request->input('order_number');
        $contact = $request->input('contact');

        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->where(function ($q) use ($contact) {
                $q->where('customer_email', $contact)
                    ->orWhere('customer_phone', $contact);
            })
            ->with(['items.product', 'items.variant'])
            ->first();

        if (! $order) {
            return ApiResponse::error('Đơn hàng không tồn tại hoặc thông tin xác thực không đúng.', 404);
        }

        return ApiResponse::success(new PublicOrderResource($order));
    }

    /**
     * Get logged-in customer's order history.
     */
    public function orderHistory(Request $request)
    {
        $user = $request->user();
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->latest()
            ->paginate(10);

        return ApiResponse::success(PublicOrderResource::collection($orders->items()), 'Lấy lịch sử đơn hàng thành công.', [
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
        ]);
    }

    /**
     * Get detail of logged-in customer's specific order.
     */
    public function orderDetail($orderNumber, Request $request)
    {
        $user = $request->user();
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->where('user_id', $user->id)
            ->with(['items.product', 'items.variant'])
            ->first();

        if (! $order) {
            return ApiResponse::error('Không tìm thấy đơn hàng.', 404);
        }

        return ApiResponse::success(new PublicOrderResource($order));
    }

    /**
     * Submit a review for a specific product.
     */
    public function storeReview(Request $request, $idOrSlug)
    {
        $product = Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($idOrSlug) {
                $q->where('id', $idOrSlug)->orWhere('slug', $idOrSlug);
            })
            ->first();

        if (! $product) {
            return ApiResponse::error('Sản phẩm không tồn tại.', 404);
        }

        $user = $request->user('sanctum');

        $rules = [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ];

        if (! $user) {
            $rules['customer_name'] = 'required|string|max:255';
            $rules['customer_email'] = 'required|email|max:255';
        } else {
            $rules['customer_name'] = 'nullable|string|max:255';
            $rules['customer_email'] = 'nullable|email|max:255';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $customerName = $request->input('customer_name') ?: ($user ? $user->name : null);
        $customerEmail = $request->input('customer_email') ?: ($user ? $user->email : null);

        // Verify that the customer has purchased the product
        $hasPurchased = false;
        if ($user) {
            $hasPurchased = Order::query()
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('customer_email', $user->email);
                })
                ->where('status', '!=', 'cancelled')
                ->whereHas('items', function ($q) use ($product) {
                    $q->where('product_id', $product->id);
                })
                ->exists();
        } else {
            $hasPurchased = Order::query()
                ->where('customer_email', $customerEmail)
                ->where('status', '!=', 'cancelled')
                ->whereHas('items', function ($q) use ($product) {
                    $q->where('product_id', $product->id);
                })
                ->exists();
        }

        if (! $hasPurchased) {
            return ApiResponse::error('Bạn chỉ có thể đánh giá sản phẩm sau khi đã mua hàng.', 403);
        }

        $review = Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $user ? $user->id : null,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'rating' => (int) $request->input('rating'),
            'comment' => $request->input('comment'),
            'is_visible' => true,
        ]);

        return ApiResponse::success(new PublicReviewResource($review), 'Gửi đánh giá thành công.');
    }

    /**
     * Handle Instant Payment Notification (IPN) from VNPAY.
     */
    public function vnpayIpn(Request $request)
    {
        $params = $request->all();
        $logParams = $params;
        unset($logParams['vnp_SecureHash'], $logParams['vnp_SecureHashType']);
        Log::info('Received VNPAY IPN.', $logParams);

        $vnpayService = app(VNPAYService::class);
        if (! $vnpayService->verifyIpnSignature($params)) {
            Log::warning('VNPAY IPN signature verification failed.');

            return response()->json([
                'RspCode' => '97',
                'Message' => 'Invalid signature',
            ]);
        }

        $orderNumber = $params['vnp_TxnRef'] ?? null;
        $responseCode = $params['vnp_ResponseCode'] ?? null;
        $transactionStatus = $params['vnp_TransactionStatus'] ?? null;

        if (! $orderNumber) {
            return response()->json([
                'RspCode' => '01',
                'Message' => 'Order not found',
            ]);
        }

        $order = Order::where('order_number', $orderNumber)->first();
        if (! $order) {
            Log::warning("VNPAY IPN order not found: {$orderNumber}");

            return response()->json([
                'RspCode' => '01',
                'Message' => 'Order not found',
            ]);
        }

        // Check if the order amount matches the VNPAY transaction amount (vnp_Amount is multiplied by 100)
        $vnpAmount = (int) ($params['vnp_Amount'] ?? 0);
        $orderAmount = (int) round($order->grand_total) * 100;
        if ($vnpAmount !== $orderAmount) {
            Log::warning("VNPAY IPN amount mismatch for order {$orderNumber}. VNPAY: {$vnpAmount}, Order: {$orderAmount}");

            return response()->json([
                'RspCode' => '04',
                'Message' => 'Invalid amount',
            ]);
        }

        $paymentSucceeded = $responseCode === '00' && $transactionStatus === '00';

        try {
            $result = DB::transaction(function () use ($order, $params, $paymentSucceeded): array {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                $paymentTransaction = $this->paymentTransactionService->recordVnpayIpn(
                    $lockedOrder,
                    $params,
                    $paymentSucceeded,
                );

                if (! $paymentTransaction->wasRecentlyCreated) {
                    return [
                        'RspCode' => '02',
                        'Message' => 'Transaction already confirmed',
                        'order' => $lockedOrder,
                        'status_changed' => false,
                    ];
                }

                if ($lockedOrder->payment_status === 'paid') {
                    return [
                        'RspCode' => '02',
                        'Message' => 'Order already confirmed',
                        'order' => $lockedOrder,
                        'status_changed' => false,
                    ];
                }

                if ($paymentSucceeded && $lockedOrder->status === 'cancelled') {
                    return [
                        'RspCode' => '02',
                        'Message' => 'Order already cancelled',
                        'order' => $lockedOrder,
                        'status_changed' => false,
                    ];
                }

                $oldStatus = $lockedOrder->status;
                $oldPaymentStatus = $lockedOrder->payment_status;
                $newStatus = $paymentSucceeded && $oldStatus === 'pending' ? 'processing' : $oldStatus;
                $newPaymentStatus = $paymentSucceeded ? 'paid' : 'failed';
                $lockedOrder->update([
                    'status' => $newStatus,
                    'payment_status' => $newPaymentStatus,
                ]);
                $this->paymentTransactionService->syncInitialTransaction($lockedOrder);

                if ($oldStatus !== $newStatus || $oldPaymentStatus !== $newPaymentStatus) {
                    OrderStatusHistory::query()->create([
                        'order_id' => $lockedOrder->id,
                        'from_status' => $oldStatus,
                        'to_status' => $newStatus,
                        'from_payment_status' => $oldPaymentStatus,
                        'to_payment_status' => $newPaymentStatus,
                        'note' => 'Cập nhật thanh toán từ VNPAY IPN',
                        'created_at' => now(),
                    ]);
                }

                return [
                    'RspCode' => '00',
                    'Message' => 'Confirm success',
                    'order' => $lockedOrder->fresh(),
                    'status_changed' => $oldStatus !== $newStatus,
                ];
            });
        } catch (\Throwable $exception) {
            Log::error('Failed to process VNPAY IPN.', [
                'order_number' => $orderNumber,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'RspCode' => '99',
                'Message' => 'Database error',
            ]);
        }

        if ($result['status_changed']) {
            SendOrderStatusEmail::dispatch($result['order']->id, $result['order']->customer_email)->afterCommit();
        }

        return response()->json([
            'RspCode' => $result['RspCode'],
            'Message' => $result['Message'],
        ]);
    }

    /**
     * Show mock VNPAY payment page.
     */
    public function vnpayMockPayment(Request $request)
    {
        $orderId = $request->query('order_id');
        $amount = $request->query('amount');
        $redirectUrl = $request->query('redirect_url');

        $order = Order::where('order_number', $orderId)->firstOrFail();

        return view('vnpay_mock', [
            'order' => $order,
            'amount' => $amount,
            'redirectUrl' => $redirectUrl,
        ]);
    }

    /**
     * Submit mock VNPAY payment simulation.
     */
    public function vnpayMockSubmit(Request $request)
    {
        $orderId = $request->input('order_id');
        $amount = $request->input('amount');
        $status = $request->input('status'); // 'success' or 'cancel'
        $redirectUrl = $request->input('redirect_url');

        $paymentMethod = PaymentMethod::where('method_code', 'vnpay')->firstOrFail();
        $settings = $paymentMethod->settings;
        $tmnCode = $settings['tmn_code'] ?? 'mock';
        $hashSecret = $settings['hash_secret'] ?? 'mock';

        $ipnParams = [
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => $amount * 100,
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toan don hang '.$orderId,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $redirectUrl,
            'vnp_TxnRef' => $orderId,
            'vnp_Version' => '2.1.0',
            'vnp_ResponseCode' => $status === 'success' ? '00' : '24', // '24' is user cancelled
            'vnp_TransactionStatus' => $status === 'success' ? '00' : '02',
            'vnp_TransactionNo' => 'MOCK_VNP_TRANS_'.time(),
        ];

        // Generate signature using sorted parameters and configured hashSecret
        ksort($ipnParams);
        $hashData = '';
        $i = 0;
        foreach ($ipnParams as $key => $value) {
            if ($i == 1) {
                $hashData .= '&'.urlencode($key).'='.urlencode($value);
            } else {
                $hashData .= urlencode($key).'='.urlencode($value);
                $i = 1;
            }
        }

        $ipnParams['vnp_SecureHash'] = hash_hmac('sha512', $hashData, $hashSecret);

        // Call vnpayIpn internally via request object simulation
        $ipnRequest = Request::create(route('api.payment.vnpay.ipn'), 'GET', $ipnParams);
        $this->vnpayIpn($ipnRequest);

        // Build redirect URL matching VNPAY return params format
        $parsedUrl = parse_url($redirectUrl);
        $queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }
        $queryParams = array_merge($queryParams, $ipnParams);
        $newQuery = http_build_query($queryParams);

        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'].'://' : '';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        $port = isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : '';
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';

        $finalRedirectUrl = $scheme.$host.$port.$path.'?'.$newQuery;

        return redirect($finalRedirectUrl);
    }
}
