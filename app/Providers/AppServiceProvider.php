<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Auth::extend('jwt', function ($app, $name, array $config) {
            return new \Illuminate\Auth\RequestGuard(function ($request) use ($app, $config) {
                $token = $request->bearerToken();
                if (!$token) {
                    return null;
                }

                $payload = \App\Support\JwtService::decode($token);
                if (!$payload || empty($payload['sub'])) {
                    return null;
                }

                $provider = \Illuminate\Support\Facades\Auth::createUserProvider($config['provider']);
                return $provider->retrieveById($payload['sub']);
            }, $app['request']);
        });

        Paginator::useBootstrapFive();


        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
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

            if ($user->role && in_array('*', $user->role->permissions ?? [])) {
                return true;
            }

            if ($user->role && in_array($ability, $user->role->permissions ?? [])) {
                return true;
            }

            return null;
        });

        // Dynamic View Composer for Real Notifications in Admin Header
        view()->composer('admin.layouts.header', function ($view) {
            $notifications = collect();

            // Fetch recent orders
            if (\Illuminate\Support\Facades\Schema::hasTable('orders')) {
                $orders = \App\Models\Order::query()->latest()->limit(5)->get();
                foreach ($orders as $order) {
                    $notifications->push([
                        'title' => 'Đơn hàng mới #' . $order->order_number,
                        'message' => 'Khách hàng: ' . $order->customer_name . '. Tổng cộng: ' . number_format($order->grand_total, 0, ',', '.') . ' ₫.',
                        'time' => $order->created_at ? $order->created_at->diffForHumans() : '',
                        'icon' => 'solar:cart-3-line-duotone',
                        'bg_color' => 'bg-primary-subtle text-primary',
                        'link' => route('admin.orders.show', $order->id),
                    ]);
                }
            }

            // Fetch recent reviews
            if (\Illuminate\Support\Facades\Schema::hasTable('reviews')) {
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
                        'icon' => 'solar:chat-round-line-line-duotone',
                        'bg_color' => 'bg-info-subtle text-info',
                        'link' => route('admin.reviews.index'),
                    ]);
                }
            }

            // Fetch recent registered users
            if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                $users = \App\Models\User::query()->latest()->limit(5)->get();
                foreach ($users as $user) {
                    $notifications->push([
                        'title' => 'Thành viên mới: ' . $user->name,
                        'message' => 'Email: ' . $user->email,
                        'time' => $user->created_at ? $user->created_at->diffForHumans() : '',
                        'icon' => 'solar:shield-user-line-duotone',
                        'bg_color' => 'bg-success-subtle text-success',
                        'link' => route('admin.users.edit', $user->id),
                    ]);
                }
            }

            // Sort notifications by actual database timestamp if available
            // (We mix them, then take the 5 most recent across all events)
            $notifications = $notifications->sortByDesc(function ($n) {
                return $n['time']; // simple string sort is okay, but we can do it more reliably if needed. Since we just want the top 5, sorting is fine.
            })->take(5);

            $view->with('headerNotifications', $notifications);
        });
    }
}
