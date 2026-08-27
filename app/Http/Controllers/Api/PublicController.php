<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicBrandResource;
use App\Http\Resources\PublicCategoryResource;
use App\Http\Resources\PublicOrderResource;
use App\Http\Resources\PublicPageResource;
use App\Http\Resources\PublicPostResource;
use App\Http\Resources\PublicProductResource;
use App\Http\Resources\PublicReviewResource;
use App\Jobs\SendOrderStatusEmail;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\ProjectSetting;
use App\Models\Review;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\Catalog\ProductQueryService;
use App\Services\Catalog\ProductVariantResolver;
use App\Services\LanguageRegistry;
use App\Services\LocalizedContent;
use App\Services\LocalizedSlugService;
use App\Services\MultilingualSettings;
use App\Services\OrderStateTransitionService;
use App\Services\Orders\OrderCreationService;
use App\Services\Orders\OrderDraft;
use App\Services\OrderStockService;
use App\Services\PaymentTransactionService;
use App\Services\PromotionService;
use App\Services\SePayService;
use App\Services\ShippingService;
use App\Services\VNPAYService;
use App\Support\ApiResponse;
use App\Support\FeatureGate;
use App\Support\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PublicController extends Controller
{
    public function __construct(
        private readonly OrderStockService $orderStockService,
        private readonly OrderStateTransitionService $orderStateTransitionService,
        private readonly PaymentTransactionService $paymentTransactionService,
        private readonly ProductVariantResolver $variantResolver,
        private readonly ProductQueryService $productQuery,
        private readonly OrderCreationService $orderCreation,
        private readonly PromotionService $promotionService,
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly LocalizedContent $localizedContent,
        private readonly MultilingualSettings $multilingual,
        private readonly FeatureGate $features,
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

        $settings['multilingual'] = $this->multilingual->publicConfig();
        $featureStates = FeatureSetting::query()
            ->whereIn('feature_code', config('features.codes', []))
            ->pluck('is_enabled', 'feature_code');
        $settings['features'] = collect(config('features.codes', []))
            ->mapWithKeys(fn (string $code) => [$code => (bool) $featureStates->get($code, false)])
            ->all();

        return ApiResponse::success($settings);
    }

    public function languages(LanguageRegistry $languages)
    {
        return ApiResponse::success($languages->active()->map(fn ($language) => [
            'code' => $language->code,
            'name' => $language->name,
            'native_name' => $language->native_name,
            'regional' => $language->regional,
            'is_default' => (bool) $language->is_default,
            'is_content_fallback' => (bool) $language->is_content_fallback,
        ])->values());
    }

    /**
     * Get category tree list.
     */
    public function categories()
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['localizedSlugs', 'children' => function ($q) {
                $q->where('is_active', true)->with('localizedSlugs')->orderBy('sort_order')->orderBy('id');
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(PublicCategoryResource::collection($categories));
    }

    /**
     * Get brand list.
     */
    public function brands()
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->with('localizedSlugs')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(PublicBrandResource::collection($brands));
    }

    /**
     * Get filterable products list.
     */
    public function products(Request $request)
    {
        $products = $this->productQuery
            ->listing($request->only(['q', 'category', 'brand', 'min_price', 'max_price', 'sort_by']))
            ->paginate(12)
            ->withQueryString();

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
        $product = $this->productQuery->findActiveDetail((string) $idOrSlug);

        if (! $product) {
            return ApiResponse::error('Sản phẩm không tồn tại.', 404);
        }

        return ApiResponse::success(new PublicProductResource($product));
    }

    public function postCategories()
    {
        $content = $this->localizedContent;
        $categories = PostCategory::query()
            ->where('is_active', true)
            ->with('localizedSlugs')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PostCategory $category) => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $content->get($category, 'name'),
                'slug' => $category->canonicalSlug(),
                'description' => $content->get($category, 'description'),
            ]);

        return ApiResponse::success($categories);
    }

    public function posts(Request $request)
    {
        $query = Post::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->with('localizedSlugs');

        if ($request->filled('category')) {
            $category = $this->localizedSlugs->find(PostCategory::class, $request->input('category'), app()->getLocale());
            $query->where('category_id', $category?->id ?? 0);
        }
        if ($request->filled('q')) {
            $keyword = $request->input('q');
            $locale = app()->getLocale();
            $query->where(fn ($query) => $query->where("title->{$locale}", 'like', "%{$keyword}%")
                ->orWhere("summary->{$locale}", 'like', "%{$keyword}%"));
        }

        $posts = $query->latest('published_at')->paginate(12)->withQueryString();

        return ApiResponse::success(PublicPostResource::collection($posts->items()), null, [
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'per_page' => $posts->perPage(),
            'total' => $posts->total(),
        ]);
    }

    public function postDetail(string $idOrSlug)
    {
        $post = $this->localizedSlugs->find(Post::class, $idOrSlug, app()->getLocale());
        if (! $post || ! $post->is_active || ($post->published_at && $post->published_at->isFuture())) {
            return ApiResponse::error('Bài viết không tồn tại.', 404);
        }

        $post->load(['localizedSlugs', 'category.localizedSlugs']);

        return ApiResponse::success(new PublicPostResource($post));
    }

    /**
     * Storefront navigation for a menu key.
     *
     * Shares App\Services\MenuService with the Blade layout, so a headless
     * client and the server-rendered pages cannot drift apart.
     */
    public function menu(string $key)
    {
        $items = app(\App\Services\MenuService::class)->tree($key);

        abort_if($items->isEmpty() && ! \App\Models\Menu::query()->where('key', $key)->where('is_active', true)->exists(), 404);

        return ApiResponse::success(['key' => $key, 'items' => $items]);
    }

    public function pages()
    {
        $pages = Page::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with('localizedSlugs')
            ->latest('published_at')
            ->get();

        return ApiResponse::success(PublicPageResource::collection($pages));
    }

    public function pageDetail(string $idOrSlug)
    {
        $page = $this->localizedSlugs->find(Page::class, $idOrSlug, app()->getLocale());
        if (! $page || ! $page->is_active || ! $page->published_at || $page->published_at->isFuture()) {
            return ApiResponse::error('Trang không tồn tại.', 404);
        }

        $page->load('localizedSlugs');

        return ApiResponse::success(new PublicPageResource($page));
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

        $apiUser = $request->user('sanctum');
        $customerId = ($apiUser && $apiUser->role_id === null) ? $apiUser->id : null;
        if ($voucher->reachedPerUserLimit($customerId, null)) {
            return ApiResponse::error('Bạn đã sử dụng hết số lượt cho phép của mã giảm giá này.', 422);
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
        $paymentFeature = $paymentMethod->method_code === 'cod' ? 'cod_order' : 'online_payment';
        if (! $this->features->enabled($paymentFeature)) {
            return ApiResponse::error($this->features->unavailableMessage(), 403);
        }
        if ($request->filled('voucher_code') && ! $this->features->enabled('voucher')) {
            return ApiResponse::error($this->features->unavailableMessage(), 403);
        }
        if ($paymentMethod->type === 'connected' && ! in_array($paymentMethod->method_code, ['vnpay', SePayService::METHOD_CODE], true)) {
            return ApiResponse::error('Cổng thanh toán này chưa được tích hợp cho checkout.', 422);
        }
        // Checked before the order exists: an unpayable SePay order would otherwise
        // have to be created, stock-deducted and then rolled back.
        if ($paymentMethod->method_code === SePayService::METHOD_CODE && ! app(SePayService::class)->isConfigured($paymentMethod)) {
            return ApiResponse::error('Cổng thanh toán SePay chưa được cấu hình đầy đủ.', 422);
        }

        $shipping = app(ShippingService::class)->getSettings();
        $shippingFee = data_get($shipping, 'flat_rate.enabled')
            ? (float) data_get($shipping, 'flat_rate.fee', 0)
            : 0.0;

        $apiUser = $request->user('sanctum');
        if ($apiUser && $apiUser->role_id !== null) {
            return ApiResponse::error('Tài khoản quản trị không thể dùng để tạo đơn hàng khách.', 403);
        }

        $customerId = $apiUser?->id;
        try {
            $order = DB::transaction(function () use ($request, $customerId, $shippingFee) {
                $orderItems = [];

                foreach ($request->input('items') as $item) {
                    $product = Product::query()->lockForUpdate()->find($item['product_id']);
                    if (! $product || ! $product->is_active) {
                        throw new \DomainException("Sản phẩm ID {$item['product_id']} không tồn tại hoặc đã ngừng kinh doanh.");
                    }

                    $variant = null;
                    if ($product->usesVariantInventory()) {
                        $variant = $this->variantResolver->resolve($product, $item['option_value_ids'] ?? [], true);
                    } elseif (! empty($item['variant_id'])) {
                        throw new \DomainException('API mới không nhận variant_id trực tiếp. Hãy gửi option_value_ids để chọn SKU.');
                    }

                    $quantity = (int) $item['quantity'];
                    if ($product->manage_stock && ! $product->usesVariantInventory() && $product->stock_quantity < $quantity) {
                        throw new \DomainException("Sản phẩm {$product->name} đã hết hàng hoặc không đủ tồn kho.");
                    }

                    $orderItems[] = $this->orderCreation->priceLine($product, $variant, $quantity);
                }

                $voucher = null;
                $voucherDiscount = 0.0;
                $discountableSubtotal = $this->orderCreation->discountableSubtotal($orderItems);
                if ($request->filled('voucher_code')) {
                    $voucher = Voucher::query()
                        ->where('code', strtoupper($request->input('voucher_code')))
                        ->lockForUpdate()
                        ->first();
                    if (! $voucher || ! $voucher->isValidForOrder($discountableSubtotal, $customerId, $request->input('customer_email'))) {
                        throw new \DomainException('Mã giảm giá không hợp lệ cho đơn hàng này.');
                    }
                    $voucherDiscount = $voucher->calculateDiscount($discountableSubtotal);
                }
                $order = $this->orderCreation->create(new OrderDraft(
                    customerName: $request->customer_name,
                    customerEmail: $request->customer_email,
                    customerPhone: $request->customer_phone,
                    shippingAddress: $request->shipping_address,
                    paymentMethod: $request->payment_method,
                    lines: $orderItems,
                    extraDiscount: $voucherDiscount,
                    shippingFee: $shippingFee,
                    notes: $request->notes,
                    userId: $customerId,
                    locale: $request->attributes->get('content_locale', app()->getLocale()),
                    paymentSource: 'public_checkout',
                    historyNote: 'Tạo đơn hàng từ checkout API',
                    stockNote: 'Xuất kho từ checkout API',
                ));

                if ($voucher) {
                    $voucher->increment('used_count');
                    VoucherUsage::query()->create([
                        'voucher_id' => $voucher->id,
                        'user_id' => $customerId,
                        'order_id' => $order->id,
                        'customer_email' => $order->customer_email,
                        'used_at' => now(),
                    ]);
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
                            VoucherUsage::query()->where('order_id', $lockedOrder->id)->delete();
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
        if ($order->payment_method === SePayService::METHOD_CODE) {
            // The order is already placed and stock is committed; the settings were
            // validated above, so a null here means they changed mid-request. Report
            // the order rather than failing it — admin can resend the payment details.
            $instructions = app(SePayService::class)->checkoutInstructions($order);
            if ($instructions === null) {
                Log::error('SePay checkout instructions unavailable after order creation.', [
                    'order_number' => $order->order_number,
                ]);
            }
            $responseData['payment'] = $instructions;
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
        $product = $this->localizedSlugs->find(Product::class, $idOrSlug, app()->getLocale());
        if ($product && ! $product->is_active) {
            $product = null;
        }

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
            $rules['order_number'] = 'required|string|max:100';
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
                ->where('status', 'completed')
                ->whereHas('items', function ($q) use ($product) {
                    $q->where('product_id', $product->id);
                })
                ->exists();
        } else {
            $hasPurchased = Order::query()
                ->where('order_number', $request->input('order_number'))
                ->where('customer_email', $customerEmail)
                ->where('status', 'completed')
                ->whereHas('items', function ($q) use ($product) {
                    $q->where('product_id', $product->id);
                })
                ->exists();
        }

        if (! $hasPurchased) {
            return ApiResponse::error('Bạn chỉ có thể đánh giá sản phẩm sau khi đã mua hàng.', 403);
        }

        $review = DB::transaction(function () use ($product, $user, $customerName, $customerEmail, $request) {
            // Serializes review creation per product so concurrent submissions cannot both pass the duplicate check.
            Product::query()->lockForUpdate()->findOrFail($product->id);

            $existingReview = Review::query()
                ->where('product_id', $product->id)
                ->when(
                    $user,
                    fn ($query) => $query->where(function ($query) use ($user) {
                        $query->where('user_id', $user->id)
                            ->orWhereRaw('LOWER(customer_email) = ?', [strtolower((string) $user->email)]);
                    }),
                    fn ($query) => $query->whereRaw('LOWER(customer_email) = ?', [strtolower((string) $customerEmail)]),
                )
                ->exists();

            if ($existingReview) {
                return null;
            }

            return Review::query()->create([
                'product_id' => $product->id,
                'user_id' => $user ? $user->id : null,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'rating' => (int) $request->input('rating'),
                'comment' => $request->input('comment'),
                'is_visible' => true,
            ]);
        });

        if (! $review) {
            return ApiResponse::error('Bạn đã gửi đánh giá cho sản phẩm này.', 409);
        }

        return ApiResponse::success(new PublicReviewResource($review), 'Gửi đánh giá thành công.');
    }

    /**
     * Handle the browser return from VNPAY.
     *
     * This only verifies data integrity (signature) and reports the outcome. It never
     * mutates the order — the IPN callback is the single source of truth for the payment
     * state. When a validated client redirect is supplied, the customer is bounced back to
     * the storefront with a sanitized result; otherwise a JSON payload is returned.
     */
    public function vnpayReturn(Request $request)
    {
        // Only vnp_* fields are signed; strip everything else before verifying.
        $vnpParams = collect($request->query())
            ->filter(fn ($value, $key) => str_starts_with((string) $key, 'vnp_'))
            ->all();

        $vnpayService = app(VNPAYService::class);
        $verified = $vnpParams !== [] && $vnpayService->verifyIpnSignature($vnpParams);

        $orderNumber = $vnpParams['vnp_TxnRef'] ?? null;
        $responseCode = $vnpParams['vnp_ResponseCode'] ?? null;
        $order = ($verified && $orderNumber)
            ? Order::query()->where('order_number', $orderNumber)->first()
            : null;

        if (! $verified) {
            $outcome = 'invalid';
        } elseif ($responseCode === '00') {
            $outcome = 'success';
        } else {
            $outcome = 'failed';
        }

        // Bounce back to the storefront when a validated redirect target is provided.
        $clientRedirect = $vnpayService->safeReturnUrl($request->query('redirect_url'));
        if ($request->filled('redirect_url') && $clientRedirect !== url('/')) {
            $separator = str_contains($clientRedirect, '?') ? '&' : '?';

            return redirect()->away($clientRedirect.$separator.http_build_query(array_filter([
                'payment' => $outcome,
                'order_number' => $orderNumber,
                'vnp_response_code' => $responseCode,
            ], fn ($value) => $value !== null)));
        }

        if (! $verified) {
            return ApiResponse::error('Chữ ký VNPAY không hợp lệ.', 400, ['payment' => 'invalid']);
        }

        if (! $order) {
            return ApiResponse::error('Không tìm thấy đơn hàng.', 404, ['payment' => $outcome]);
        }

        return ApiResponse::success([
            'payment' => $outcome,
            'response_code' => $responseCode,
            'order' => new PublicOrderResource($order),
        ], $outcome === 'success' ? 'Thanh toán thành công.' : 'Thanh toán chưa hoàn tất hoặc đã bị hủy.');
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
        if ($order->payment_method !== 'vnpay') {
            Log::warning("VNPAY IPN payment method mismatch for order {$orderNumber}.");

            return response()->json([
                'RspCode' => '02',
                'Message' => 'Order payment method mismatch',
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
                if ($lockedOrder->payment_status === 'paid') {
                    return [
                        'RspCode' => '02',
                        'Message' => 'Order already confirmed',
                        'order' => $lockedOrder,
                        'status_changed' => false,
                    ];
                }

                if (! in_array($lockedOrder->payment_status, ['pending', 'failed'], true)) {
                    return [
                        'RspCode' => '02',
                        'Message' => 'Order payment state cannot be updated',
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

                $oldStatus = $lockedOrder->status;
                $oldPaymentStatus = $lockedOrder->payment_status;
                $newStatus = $paymentSucceeded && $oldStatus === 'pending' ? 'processing' : $oldStatus;
                $newPaymentStatus = $paymentSucceeded ? 'paid' : 'failed';
                $this->orderStateTransitionService->assertCanTransition(
                    $oldStatus,
                    $newStatus,
                    $oldPaymentStatus,
                    $newPaymentStatus,
                );
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
        abort_unless(config('app.payment_mock_enabled') && app()->environment(['local', 'testing']), 404);

        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:100'],
            'redirect_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $order = Order::where('order_number', $validated['order_id'])->firstOrFail();
        $redirectUrl = app(VNPAYService::class)->safeReturnUrl($validated['redirect_url'] ?? null);

        return view('vnpay_mock', [
            'order' => $order,
            'redirectUrl' => $redirectUrl,
        ]);
    }

    /**
     * Submit mock VNPAY payment simulation.
     */
    public function vnpayMockSubmit(Request $request)
    {
        abort_unless(config('app.payment_mock_enabled') && app()->environment(['local', 'testing']), 404);

        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:success,cancel'],
            'redirect_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $order = Order::query()->where('order_number', $validated['order_id'])->firstOrFail();
        abort_if($order->payment_method !== 'vnpay' || $order->payment_status !== 'pending', 409);

        $orderId = $order->order_number;
        $amount = (int) round($order->grand_total);
        $status = $validated['status'];
        $redirectUrl = app(VNPAYService::class)->safeReturnUrl($validated['redirect_url'] ?? null);

        $paymentMethod = PaymentMethod::where('method_code', 'vnpay')->firstOrFail();
        $settings = $paymentMethod->settings;
        abort_unless(($settings['tmn_code'] ?? null) === 'mock', 404);
        $tmnCode = 'mock';
        $hashSecret = (string) ($settings['hash_secret'] ?? '');
        abort_if($hashSecret === '', 422, 'Mock hash secret is not configured.');

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
