<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Services\SiteBranding::class);

        $this->app->bind(
            \App\Contracts\TranslationProvider::class,
            \App\Services\Translation\GoogleTranslationProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        view()->composer([
            'admin.layouts.app',
            'admin.layouts.header',
            'admin.layouts.sidebar',
            'admin.invoices.show',
            'admin.orders.show',
            'auth.login',
            'auth.forgot-password',
            'auth.reset-password',
            'api.docs',
        ], function ($view) {
            $view->with('siteBranding', app(\App\Services\SiteBranding::class)->current());
        });

        RateLimiter::for('public-auth', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by(strtolower((string) $request->input('email'))),
        ]);
        RateLimiter::for('public-contact', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('public-checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('public-tracking', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('public-review', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by(strtolower((string) $request->input('email'))),
        ]);
        RateLimiter::for('admin-translation', fn (Request $request) => [
            Limit::perMinute(20)->by((string) ($request->user()?->id ?: $request->ip())),
            Limit::perDay(500)->by((string) ($request->user()?->id ?: $request->ip())),
        ]);
        RateLimiter::for('admin-page-inline', fn (Request $request) => [
            Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip())),
        ]);

        Paginator::useBootstrapFive();


        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            if ($notifiable instanceof User && $notifiable->role_id === null) {
                return route('customer.password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]);
            }

            return route('admin.password.reset', [
                'locale' => app()->getLocale() ?: config('app.locale', 'vi'),
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        // Dynamic Role-based Permission Gate
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            if ($user->role && $user->role->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        // Dynamic View Composer for Real Notifications in Admin Header
        view()->composer('admin.layouts.header', function ($view) {
            $notifications = collect();
            $user = auth()->user();

            // Fetch recent orders
            if ($user?->can('manage_orders') && \Illuminate\Support\Facades\Schema::hasTable('orders')) {
                $orders = \App\Models\Order::query()->latest()->limit(5)->get();
                foreach ($orders as $order) {
                    $notifications->push([
                        'title' => 'Đơn hàng mới #' . $order->order_number,
                        'message' => 'Khách hàng: ' . $order->customer_name . '. Tổng cộng: ' . number_format($order->grand_total, 0, ',', '.') . ' ₫.',
                        'time' => $order->created_at ? $order->created_at->diffForHumans() : '',
                        'timestamp' => $order->created_at?->getTimestamp() ?? 0,
                        'icon' => 'solar:cart-3-line-duotone',
                        'bg_color' => 'bg-primary-subtle text-primary',
                        'link' => route('admin.orders.show', $order->id),
                    ]);
                }
            }

            // Fetch recent reviews
            if ($user?->can('manage_reviews') && \Illuminate\Support\Facades\Schema::hasTable('reviews')) {
                $reviews = \App\Models\Review::query()->latest()->limit(5)->get();
                foreach ($reviews as $review) {
                    $prodName = 'Sản phẩm';
                    if ($review->product) {
                        $name = $review->product->name;
                        $prodName = is_array($name) ? ($name['vi'] ?? array_values($name)[0] ?? 'Sản phẩm') : $name;
                    }
                    $notifications->push([
                        'title' => 'Đánh giá mới từ ' . ($review->customer_name ?? 'Khách hàng'),
                        'message' => 'Đánh giá ' . $review->rating . ' sao cho ' . $prodName,
                        'time' => $review->created_at ? $review->created_at->diffForHumans() : '',
                        'timestamp' => $review->created_at?->getTimestamp() ?? 0,
                        'icon' => 'solar:chat-round-line-line-duotone',
                        'bg_color' => 'bg-info-subtle text-info',
                        'link' => route('admin.reviews.index'),
                    ]);
                }
            }

            // Fetch recent registered users
            if ($user?->can('manage_users') && \Illuminate\Support\Facades\Schema::hasTable('users')) {
                $users = \App\Models\User::query()->latest()->limit(5)->get();
                foreach ($users as $user) {
                    $notifications->push([
                        'title' => 'Thành viên mới: ' . $user->name,
                        'message' => 'Email: ' . $user->email,
                        'time' => $user->created_at ? $user->created_at->diffForHumans() : '',
                        'timestamp' => $user->created_at?->getTimestamp() ?? 0,
                        'icon' => 'solar:shield-user-line-duotone',
                        'bg_color' => 'bg-success-subtle text-success',
                        'link' => route('admin.users.edit', $user->id),
                    ]);
                }
            }

            // Sort notifications by actual database timestamp if available
            // (We mix them, then take the 5 most recent across all events)
            $notifications = $notifications->sortByDesc(function ($n) {
                return $n['timestamp'];
            })->take(5);

            $view->with('headerNotifications', $notifications);
        });
    }
}
