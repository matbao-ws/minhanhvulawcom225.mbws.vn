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
        // Scoped: a storefront page renders dozens of regions and must not
        // issue a query per region.
        $this->app->scoped(\App\Services\SiteContentService::class);
        // Same reason: a repeatable region asks for its item ids once per render,
        // and a page can hold many of them.
        $this->app->scoped(\App\Services\SiteListService::class);

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
        // Resolve the effective SMTP configuration the first time anything asks for
        // the mailer, so the admin screen outranks .env for every mail in the system
        // — customer order updates, contact form, password reset, invoices, not just
        // the store-owner notification that used to configure it inline.
        //
        // Hooked on resolution rather than run here directly: this fires only when
        // mail is actually used, which keeps the settings lookup off every web
        // request and out of `migrate` on a database that has no settings table yet.
        $this->app->resolving('mail.manager', function () {
            app(\App\Services\MailSettings::class)->apply();
        });

        view()->composer([
            'admin.layouts.app',
            'admin.layouts.header',
            'admin.layouts.sidebar',
            'admin.orders.show',
            'auth.login',
            'auth.forgot-password',
            'auth.reset-password',
            'api.docs',
            'client.layouts.app',
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

        // Storefront components live beside the rest of the client views rather
        // than in the framework default directory.
        \Illuminate\Support\Facades\Blade::anonymousComponentPath(
            resource_path('views/client/components'),
            'client',
        );


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

        // Dynamic role-based permission gate, with per-account overrides layered
        // on top. Order matters: superadmin wins outright, then an explicit
        // override decides in either direction, and only then does the role
        // speak. Returning false here is a hard denial, which is exactly what a
        // revocation must be.
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $override = $user->permissionOverrideFor($ability);
            if ($override !== null) {
                return $override;
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
            if ($user?->can('orders.view') && \Illuminate\Support\Facades\Schema::hasTable('orders')) {
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
            if ($user?->can('reviews.view') && \Illuminate\Support\Facades\Schema::hasTable('reviews')) {
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

            // Fetch recent staff accounts.
            //
            // Restricted to users that have a role: this notification is gated on
            // `users.view`, titled "Thành viên mới" and links to the staff edit form,
            // so listing customers here both mislabelled them and pointed the admin
            // at a form that does not manage them. Customers have their own screen.
            if ($user?->can('users.view') && \Illuminate\Support\Facades\Schema::hasTable('users')) {
                $staffAccounts = \App\Models\User::query()
                    ->whereNotNull('role_id')
                    ->latest()
                    ->limit(5)
                    ->get();

                // Named `$staff`, not `$user`: reusing the outer name left the
                // authenticated user pointing at the last row of this loop, which
                // would silently break the permission check of any block added after.
                foreach ($staffAccounts as $staff) {
                    $notifications->push([
                        'title' => 'Thành viên mới: ' . $staff->name,
                        'message' => 'Email: ' . $staff->email,
                        'time' => $staff->created_at ? $staff->created_at->diffForHumans() : '',
                        'timestamp' => $staff->created_at?->getTimestamp() ?? 0,
                        'icon' => 'solar:shield-user-line-duotone',
                        'bg_color' => 'bg-success-subtle text-success',
                        'link' => route('admin.users.edit', $staff->id),
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
