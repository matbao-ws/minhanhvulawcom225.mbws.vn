<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureSetting;
use App\Models\ProjectSubscription;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Key Metrics
        $totalOrders = Order::query()->count();
        $totalRevenue = Order::query()->where('status', 'completed')->sum('grand_total');
        $completedOrders = Order::query()->where('status', 'completed')->count();
        $processingOrders = Order::query()->whereIn('status', ['pending', 'processing'])->count();

        // 2. Advanced E-commerce Metrics
        $todayRevenue = Order::query()
            ->where('status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('grand_total');

        $todayOrders = Order::query()
            ->whereDate('created_at', Carbon::today())
            ->count();

        // AOV = Total Revenue / Completed Orders
        $aov = $completedOrders > 0 ? ($totalRevenue / $completedOrders) : 0;

        // Completed Rate = (Completed Orders / Total Orders) * 100%
        $completedRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0;

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
        $topCustomers = Order::query()
            ->select(
                'customer_name',
                'customer_email',
                'customer_phone',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(grand_total) as total_spent')
            )
            ->where('status', 'completed')
            ->groupBy('customer_name', 'customer_email', 'customer_phone')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        // 5. Revenue & Orders chart data (last 30 days)
        $startDate = Carbon::now()->subDays(29)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $chartData = Order::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN grand_total ELSE 0 END) as revenue'),
                DB::raw('COUNT(*) as order_count')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        // Fill in missing dates to make a continuous chart
        $dates = [];
        $revenueSeries = [];
        $ordersSeries = [];

        $chartDataMap = $chartData->pluck('revenue', 'date')->toArray();
        $chartCountMap = $chartData->pluck('order_count', 'date')->toArray();

        for ($i = 29; $i >= 0; $i--) {
            $dateStr = Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = Carbon::now()->subDays($i)->format('d/m');
            $revenueSeries[] = (float) ($chartDataMap[$dateStr] ?? 0);
            $ordersSeries[] = (int) ($chartCountMap[$dateStr] ?? 0);
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

        return view('admin.dashboard.index', [
            'subscription' => ProjectSubscription::query()->with('package')->first(),
            'enabledFeatureCount' => FeatureSetting::query()->where('is_enabled', true)->count(),
            'metrics' => [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'completed_orders' => $completedOrders,
                'processing_orders' => $processingOrders,
                'today_revenue' => $todayRevenue,
                'today_orders' => $todayOrders,
                'aov' => $aov,
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
        ]);
    }

    public function notifications()
    {
        $notifications = collect();
        $q = request('q');
        $type = request('type');

        // Fetch recent orders
        if (\Illuminate\Support\Facades\Schema::hasTable('orders') && (!$type || $type === 'orders')) {
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
        if (\Illuminate\Support\Facades\Schema::hasTable('reviews') && (!$type || $type === 'reviews')) {
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
        if (\Illuminate\Support\Facades\Schema::hasTable('users') && (!$type || $type === 'users')) {
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
