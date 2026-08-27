<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\FeatureSetting;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $canViewOrders = request()->user()->can('orders.view');
        if (! $canViewOrders) {
            return view('admin.dashboard.index', compact('canViewOrders'));
        }

        // 1. Key Metrics
        $monthlyRevenue = Order::query()
            ->where('status', 'completed')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('grand_total');

        $pendingOrders = Order::query()->where('status', 'pending')->count();
        $processingOrdersCount = Order::query()->where('status', 'processing')->count();
        $completedOrdersCount = Order::query()->where('status', 'completed')->count();

        // 2. Subtext/Secondary Metrics
        $todayRevenue = Order::query()
            ->where('status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('grand_total');

        $todayPendingOrders = Order::query()
            ->where('status', 'pending')
            ->whereDate('created_at', Carbon::today())
            ->count();

        $todayProcessingOrders = Order::query()
            ->where('status', 'processing')
            ->whereDate('created_at', Carbon::today())
            ->count();

        $totalOrders = Order::query()->count();
        $totalRevenue = Order::query()
            ->where('status', 'completed')
            ->sum('grand_total');
        $completedRate = $totalOrders > 0 ? round(($completedOrdersCount / $totalOrders) * 100, 1) : 0;

        // 3. Top Selling Products (Top 5)
        $topProducts = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('order_items')) {
            $topProducts = \App\Models\OrderItem::query()
                ->select(
                    'product_id',
                    'product_name',
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('SUM(total) as total_revenue')
                )
                ->whereHas('order', function ($query) {
                    $query->where('status', 'completed');
                })
                ->groupBy('product_id', 'product_name')
                ->orderByDesc('total_quantity')
                ->limit(5)
                ->get();
        }

        // 4. Top Customers (Top 5 VIPs)
        $registeredTopCustomers = Order::query()
            ->select(
                'user_id',
                DB::raw('MAX(customer_name) as customer_name'),
                DB::raw('MAX(customer_email) as customer_email'),
                DB::raw('MAX(customer_phone) as customer_phone'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(grand_total) as total_spent')
            )
            ->where('status', 'completed')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->get();
        $guestTopCustomers = Order::query()
            ->select(
                DB::raw('NULL as user_id'),
                DB::raw('MAX(customer_name) as customer_name'),
                'customer_email',
                DB::raw('MAX(customer_phone) as customer_phone'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(grand_total) as total_spent')
            )
            ->where('status', 'completed')
            ->whereNull('user_id')
            ->groupBy('customer_email')
            ->get();
        $topCustomers = $registeredTopCustomers
            ->concat($guestTopCustomers)
            ->sortByDesc(fn ($customer) => (float) $customer->total_spent)
            ->take(5)
            ->values();

        // 5. Revenue & Orders chart data (Current week: Monday to Sunday)
        $dates = [];
        $revenueSeries = [];
        $ordersSeries = [];

        $startOfWeek = Carbon::now()->startOfWeek();

        for ($i = 0; $i < 7; $i++) {
            $day = (clone $startOfWeek)->addDays($i);
            $dayStr = $day->format('Y-m-d');

            $dayStats = Order::query()
                ->select(
                    DB::raw('SUM(CASE WHEN status = "completed" THEN grand_total ELSE 0 END) as revenue'),
                    DB::raw('COUNT(*) as order_count')
                )
                ->whereDate('created_at', $dayStr)
                ->first();

            $label = $day->format('d/m');

            $dates[] = $label;
            $revenueSeries[] = (float) ($dayStats->revenue ?? 0);
            $ordersSeries[] = (int) ($dayStats->order_count ?? 0);
        }

        // 6. Status Breakdown for Pie Chart
        $statusCounts = Order::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statuses = ['pending', 'processing', 'completed', 'cancelled'];
        $statusSeries = [];
        foreach ($statuses as $status) {
            $statusSeries[] = (int) ($statusCounts[$status] ?? 0);
        }

        // 7. Recent Orders
        $recentOrders = Order::query()->orderBy('created_at', 'desc')->take(5)->get();

        // 8. Annual Revenue Report (Current Year)
        $currentYear = Carbon::now()->year;
        $annualRevenueSeries = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyRev = Order::query()
                ->where('status', 'completed')
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $m)
                ->sum('grand_total');
            $annualRevenueSeries[] = (float) $monthlyRev;
        }

        // 9. Actual admin activity for the last 7 days. A storefront is not part of this core.
        $activityStart = Carbon::now()->subDays(6)->startOfDay();
        $activityCounts = AdminActivityLog::query()
            ->where('created_at', '>=', $activityStart)
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as total')
            ->groupBy('activity_date')
            ->pluck('total', 'activity_date');
        $activityDates = [];
        $activityData = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i);
            $activityDates[] = $day->format('d/m');
            $activityData[] = (int) ($activityCounts[$day->toDateString()] ?? 0);
        }

        return view('admin.dashboard.index', [
            'canViewOrders' => true,
            'enabledFeatureCount' => FeatureSetting::query()->where('is_enabled', true)->count(),
            'metrics' => [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'completed_orders' => $completedOrdersCount,
                'processing_orders' => $processingOrdersCount,
                'monthly_revenue' => $monthlyRevenue,
                'pending_orders' => $pendingOrders,
                'processing_orders_count' => $processingOrdersCount,
                'completed_orders_count' => $completedOrdersCount,
                'today_revenue' => $todayRevenue,
                'today_pending_orders' => $todayPendingOrders,
                'today_processing_orders' => $todayProcessingOrders,
                'completed_rate' => $completedRate,
            ],
            'chart' => [
                'dates' => $dates,
                'revenue' => $revenueSeries,
                'orders' => $ordersSeries,
            ],
            'statusChart' => [
                'series' => $statusSeries,
                'labels' => [
                    __('admin.orders.statuses.pending'),
                    __('admin.orders.statuses.processing'),
                    __('admin.orders.statuses.completed'),
                    __('admin.orders.statuses.cancelled'),
                ]
            ],
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'topCustomers' => $topCustomers,
            'annualChart' => [
                'year' => $currentYear,
                'data' => $annualRevenueSeries,
            ],
            'activityChart' => [
                'dates' => $activityDates,
                'data' => $activityData,
                'total' => array_sum($activityData),
            ],
        ]);
    }

    public function notifications()
    {
        $notifications = collect();
        $q = request('q');
        $type = request('type');
        $user = request()->user();

        // Fetch recent orders
        if ($user->can('orders.view') && \Illuminate\Support\Facades\Schema::hasTable('orders') && (!$type || $type === 'orders')) {
            $ordersQuery = \App\Models\Order::query()->latest();
            if (!empty($q)) {
                $ordersQuery->where(function($query) use ($q) {
                    $query->where('order_number', 'like', "%{$q}%")
                          ->orWhere('customer_name', 'like', "%{$q}%")
                          ->orWhere('customer_phone', 'like', "%{$q}%")
                          ->orWhere('status', 'like', "%{$q}%");
                });
            }
            $orders = $ordersQuery->limit(200)->get();
            foreach ($orders as $order) {
                $notifications->push((object) [
                    'title' => 'Đơn hàng mới #' . $order->order_number,
                    'message' => 'Khách hàng: ' . $order->customer_name . '. Tổng cộng: ' . number_format($order->grand_total, 0, ',', '.') . ' ₫. Trạng thái: ' . $order->status,
                    'time' => $order->created_at,
                    'icon' => 'solar:cart-3-line-duotone',
                    'bg_color' => 'bg-primary-subtle text-primary',
                    'link' => route('admin.orders.show', $order->id),
                ]);
            }
        }

        // Fetch recent reviews
        if ($user->can('reviews.view') && \Illuminate\Support\Facades\Schema::hasTable('reviews') && (!$type || $type === 'reviews')) {
            $reviewsQuery = \App\Models\Review::query()->latest();
            if (!empty($q)) {
                $reviewsQuery->where(function($query) use ($q) {
                    $query->where('customer_name', 'like', "%{$q}%")
                          ->orWhere('comment', 'like', "%{$q}%")
                          ->orWhere('rating', 'like', "%{$q}%");
                });
            }
            $reviews = $reviewsQuery->limit(200)->get();
            foreach ($reviews as $review) {
                $prodName = 'Sản phẩm';
                if ($review->product) {
                    $name = $review->product->name;
                    $prodName = is_array($name) ? ($name['vi'] ?? array_values($name)[0] ?? 'Sản phẩm') : $name;
                }
                $notifications->push((object) [
                    'title' => 'Đánh giá mới từ ' . ($review->customer_name ?? 'Khách hàng'),
                    'message' => 'Đánh giá ' . $review->rating . ' sao cho ' . $prodName . ': "' . \Illuminate\Support\Str::limit($review->comment, 80) . '"',
                    'time' => $review->created_at,
                    'icon' => 'solar:chat-round-line-line-duotone',
                    'bg_color' => 'bg-info-subtle text-info',
                    'link' => route('admin.reviews.index'),
                ]);
            }
        }

        // Fetch recent registered users
        if ($user->can('users.view') && \Illuminate\Support\Facades\Schema::hasTable('users') && (!$type || $type === 'users')) {
            $usersQuery = \App\Models\User::query()->latest();
            if (!empty($q)) {
                $usersQuery->where(function($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                });
            }
            $users = $usersQuery->limit(200)->get();
            foreach ($users as $user) {
                $notifications->push((object) [
                    'title' => 'Thành viên mới đăng ký',
                    'message' => 'Họ tên: ' . $user->name . ' (Email: ' . $user->email . ')',
                    'time' => $user->created_at,
                    'icon' => 'solar:shield-user-line-duotone',
                    'bg_color' => 'bg-success-subtle text-success',
                    'link' => route('admin.users.edit', $user->id),
                ]);
            }
        }

        // Sort by time
        $notifications = $notifications->sortByDesc('time');

        // Paginate the collection manually (15 items per page)
        $perPage = 15;
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $currentPageItems = $notifications->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginatedNotifications = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $notifications->count(),
            $perPage,
            $currentPage,
            [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );

        return view('admin.notifications.index', [
            'notifications' => $paginatedNotifications
        ]);
    }
}
