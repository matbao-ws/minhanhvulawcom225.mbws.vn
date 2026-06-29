<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProjectSetting;
use App\Models\Review;
use App\Models\Voucher;

use App\Support\ApiResponse;
use App\Mail\OrderStatusMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicController extends Controller
{
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

        return ApiResponse::success($products->items(), 'Lấy danh sách sản phẩm thành công.', [
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
            ->with(['category', 'brand', 'variants', 'reviews' => function ($q) {
                $q->where('is_visible', true)->latest();
            }])
            ->first();

        if (!$product) {
            return ApiResponse::error('Sản phẩm không tồn tại.', 404);
        }

        return ApiResponse::success($product);
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

        if (!$voucher) {
            return ApiResponse::error('Mã giảm giá không tồn tại.', 422);
        }

        if (!$voucher->is_active) {
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
            'payment_method' => 'required|string|in:cod,bank_transfer,online,sepay,stripe,vnpay',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'voucher_code' => 'nullable|string',
            'shipping_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $paymentMethod = $request->input('payment_method');
        if (in_array($paymentMethod, ['vnpay', 'sepay', 'stripe'])) {
            if (!\App\Models\Addon::isPurchased($paymentMethod)) {
                return ApiResponse::error("Tính năng thanh toán qua " . strtoupper($paymentMethod) . " chưa được mở khóa. Vui lòng mua Addon để sử dụng.", 403);
            }
        }

        $subtotal = 0.0;
        $orderItemsData = [];

        foreach ($request->input('items') as $item) {
            $product = Product::query()->where('id', $item['product_id'])->where('is_active', true)->first();
            if (!$product) {
                return ApiResponse::error("Sản phẩm ID {$item['product_id']} không tồn tại hoặc đã ngừng kinh doanh.", 422);
            }

            $price = (float) $product->price;
            $variantName = null;
            $sku = $product->sku;

            if (!empty($item['variant_id'])) {
                $variant = ProductVariant::query()->where('id', $item['variant_id'])->where('product_id', $product->id)->first();
                if (!$variant) {
                    return ApiResponse::error("Biến thể sản phẩm không hợp lệ cho {$product->name}.", 422);
                }
                if ($variant->price !== null) {
                    $price = (float) $variant->price;
                }
                $variantName = $variant->name;
                $sku = $variant->sku ?: $product->sku;
            }

            $qty = (int) $item['quantity'];

            if ($product->manage_stock && $product->stock_quantity < $qty) {
                return ApiResponse::error("Sản phẩm {$product->name} đã hết hàng hoặc không đủ tồn kho.", 422);
            }

            $total = $price * $qty;
            $subtotal += $total;

            $orderItemsData[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant_name' => $variantName,
                'sku' => $sku,
                'price' => $price,
                'quantity' => $qty,
                'total' => $total,
            ];
        }

        // Apply voucher if present
        $discount = 0.0;
        $voucher = null;
        if ($request->filled('voucher_code')) {
            $voucherCode = strtoupper($request->input('voucher_code'));
            $voucher = Voucher::query()->where('code', $voucherCode)->first();
            if ($voucher && $voucher->isValidForOrder($subtotal)) {
                $discount = $voucher->calculateDiscount($subtotal);
            } else {
                return ApiResponse::error("Mã giảm giá không hợp lệ cho đơn hàng này.", 422);
            }
        }

        $shippingFee = (float) $request->input('shipping_fee', 0.0);
        $grandTotal = max(0.0, $subtotal - $discount) + $shippingFee;

        // Try using Sanctum guard for checking logged-in user id
        $customerId = $request->user('sanctum')?->id;

        DB::beginTransaction();
        try {
            $order = Order::query()->create([
                'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'shipping_address' => $request->shipping_address,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping_fee' => $shippingFee,
                'grand_total' => $grandTotal,
                'notes' => $request->notes,
                'user_id' => $customerId ?: null,
            ]);

            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);

                // Update inventory
                $product = Product::find($itemData['product_id']);
                if ($product && $product->manage_stock) {
                    $product->decrement('stock_quantity', $itemData['quantity']);
                }
            }

            if ($voucher) {
                $voucher->increment('used_count');
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Checkout transaction failed: " . $e->getMessage());
            return ApiResponse::error('Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại.', 500);
        }

        $paymentUrl = null;
        if ($order->payment_method === 'vnpay') {
            $vnpayService = app(\App\Services\VNPAYService::class);
            $paymentUrl = $vnpayService->createPayment($order, $request->input('redirect_url'));

            if (!$paymentUrl) {
                DB::beginTransaction();
                try {
                    $order->update(['status' => 'cancelled', 'notes' => $order->notes . ' (Khởi tạo thanh toán VNPAY thất bại)']);
                    // Restore stock quantity
                    foreach ($order->items as $item) {
                        $product = Product::find($item->product_id);
                        if ($product && $product->manage_stock) {
                            $product->increment('stock_quantity', $item->quantity);
                        }
                    }
                    DB::commit();
                } catch (\Exception $ex) {
                    DB::rollBack();
                }
                return ApiResponse::error('Không thể khởi tạo giao dịch thanh toán VNPAY. Vui lòng cấu hình các trường TMN Code, Hash Secret hoặc thử lại sau.', 422);
            }
        }

        // Email dispatch
        try {
            $order->load('items');
            Mail::to($order->customer_email)->send(new OrderStatusMail($order));
            \App\Support\NotificationHelper::sendNewOrderNotification($order);
        } catch (\Exception $e) {
            Log::error("Failed to send order checkout mail: " . $e->getMessage());
        }

        $responseData = $order->toArray();
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

        if (!$order) {
            return ApiResponse::error('Đơn hàng không tồn tại hoặc thông tin xác thực không đúng.', 404);
        }

        return ApiResponse::success($order);
    }

    /**
     * Get logged-in customer's order history.
     */
    public function orderHistory(Request $request)
    {
        $user = $request->user();
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return ApiResponse::success($orders->items(), 'Lấy lịch sử đơn hàng thành công.', [
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

        if (!$order) {
            return ApiResponse::error('Không tìm thấy đơn hàng.', 404);
        }

        return ApiResponse::success($order);
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

        if (!$product) {
            return ApiResponse::error('Sản phẩm không tồn tại.', 404);
        }

        $user = $request->user('sanctum');

        $rules = [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ];

        if (!$user) {
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

        if (!$hasPurchased) {
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


        return ApiResponse::success($review, 'Gửi đánh giá thành công.');
    }

    /**
     * Handle Instant Payment Notification (IPN) from VNPAY.
     */
    public function vnpayIpn(Request $request)
    {
        $params = $request->all();
        Log::info('Received VNPAY IPN: ', $params);

        $vnpayService = app(\App\Services\VNPAYService::class);
        if (!$vnpayService->verifyIpnSignature($params)) {
            Log::warning('VNPAY IPN signature verification failed.');
            return response()->json([
                'RspCode' => '97',
                'Message' => 'Invalid signature'
            ]);
        }

        $orderNumber = $params['vnp_TxnRef'] ?? null;
        $responseCode = $params['vnp_ResponseCode'] ?? null;
        $transactionStatus = $params['vnp_TransactionStatus'] ?? null;

        if (!$orderNumber) {
            return response()->json([
                'RspCode' => '01',
                'Message' => 'Order not found'
            ]);
        }

        $order = Order::where('order_number', $orderNumber)->first();
        if (!$order) {
            Log::warning("VNPAY IPN order not found: {$orderNumber}");
            return response()->json([
                'RspCode' => '01',
                'Message' => 'Order not found'
            ]);
        }

        // Check if the order amount matches the VNPAY transaction amount (vnp_Amount is multiplied by 100)
        $vnpAmount = (int) ($params['vnp_Amount'] ?? 0);
        $orderAmount = (int) round($order->grand_total) * 100;
        if ($vnpAmount !== $orderAmount) {
            Log::warning("VNPAY IPN amount mismatch for order {$orderNumber}. VNPAY: {$vnpAmount}, Order: {$orderAmount}");
            return response()->json([
                'RspCode' => '04',
                'Message' => 'Invalid amount'
            ]);
        }

        // Check if order payment is already processed
        if ($order->payment_status === 'paid') {
            return response()->json([
                'RspCode' => '02',
                'Message' => 'Order already confirmed'
            ]);
        }

        if ($responseCode === '00' && $transactionStatus === '00') {
            // Payment success
            DB::beginTransaction();
            try {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'processing',
                ]);
                DB::commit();
                Log::info("VNPAY IPN payment success for order: {$orderNumber}");
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to update order status via VNPAY IPN: " . $e->getMessage());
                return response()->json([
                    'RspCode' => '99',
                    'Message' => 'Database error'
                ]);
            }
        } else {
            // Payment failed or cancelled
            DB::beginTransaction();
            try {
                $order->update([
                    'payment_status' => 'failed',
                ]);
                DB::commit();
                Log::info("VNPAY IPN payment failed for order: {$orderNumber} (ResponseCode: {$responseCode})");
            } catch (\Exception $e) {
                DB::rollBack();
            }
        }

        return response()->json([
            'RspCode' => '00',
            'Message' => 'Confirm success'
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
            'redirectUrl' => $redirectUrl
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

        $paymentMethod = \App\Models\PaymentMethod::where('method_code', 'vnpay')->firstOrFail();
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
            'vnp_OrderInfo' => 'Thanh toan don hang ' . $orderId,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $redirectUrl,
            'vnp_TxnRef' => $orderId,
            'vnp_Version' => '2.1.0',
            'vnp_ResponseCode' => $status === 'success' ? '00' : '24', // '24' is user cancelled
            'vnp_TransactionStatus' => $status === 'success' ? '00' : '02',
            'vnp_TransactionNo' => 'MOCK_VNP_TRANS_' . time(),
        ];

        // Generate signature using sorted parameters and configured hashSecret
        ksort($ipnParams);
        $hashData = "";
        $i = 0;
        foreach ($ipnParams as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
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

        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';

        $finalRedirectUrl = $scheme . $host . $port . $path . '?' . $newQuery;

        return redirect($finalRedirectUrl);
    }
}
