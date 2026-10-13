<?php

namespace App\Providers;

use App\Contracts\TranslationProvider;
use App\Mail\Transport\MatbaoGatewayTransport;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Voucher;
use App\Services\AiGateway\AiUsage;
use App\Services\AiGateway\GatewayClient;
use App\Services\AiGateway\PostDraftService;
use App\Services\Analytics\AnalyticsReport;
use App\Services\CloudinaryCredentials;
use App\Services\LanguageRegistry;
use App\Services\MailSettings;
use App\Services\Packages\PackageLimits;
use App\Services\Packages\PackageUsageAlerts;
use App\Services\Seo\SeoMetaFactory;
use App\Services\Seo\SiteEmbeds;
use App\Services\SiteBranding;
use App\Services\SiteContentService;
use App\Services\SiteListService;
use App\Services\SiteSectionService;
use App\Services\Translation\GoogleTranslationProvider;
use App\Services\WordPressImageImportService;
use App\Support\AdminTours;
use App\Support\ClientEditorChrome;
use App\Support\CoreUpdate\ArtisanRunner;
use App\Support\CoreUpdate\ReleaseVerifier;
use App\Support\ErrorReporting\GatewayErrorReporter;
use App\Support\FeatureGate;
use App\Support\LocaleUrl;
use App\Support\Seo\SeoMeta;
use App\Support\Seo\StructuredData;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SiteBranding::class);
        // Holds the request's error summaries until the response is out (one flush per request).
        $this->app->scoped(GatewayErrorReporter::class);
        $this->app->bind(ReleaseVerifier::class, fn () => new ReleaseVerifier(
            array_values(array_filter((array) config('core_update.public_keys'))),
        ));
        $this->app->bind(ArtisanRunner::class, fn () => new ArtisanRunner(base_path()));
        // One image downloader per import run: posts, pages and products share its cache and report.
        $this->app->scoped(WordPressImageImportService::class);
        // Scoped: a storefront page renders dozens of regions and must not
        // issue a query per region.
        $this->app->scoped(SiteContentService::class);
        // Same reason: a repeatable region asks for its item ids once per render,
        // and a page can hold many of them.
        $this->app->scoped(SiteListService::class);
        // Same again: every section on the page asks how it should render.
        $this->app->scoped(SiteSectionService::class);
        // Read by both the <head> partial and the footer partial of one page.
        $this->app->scoped(SiteEmbeds::class);
        // Media pages and upload services consult the same encrypted
        // Cloudinary setting several times in one request.
        $this->app->scoped(CloudinaryCredentials::class);
        // Scoped so an importer can ask, after the loop, whether any row hit the limit.
        $this->app->scoped(PackageLimits::class);

        $this->app->bind(
            TranslationProvider::class,
            GoogleTranslationProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Storefront URLs of the default language have no /{locale} prefix (App\Support\LocaleUrl):
        // every route('client.…') — canonical, hreflang, sitemap, menus, the site index — is built
        // from the prefixed route and loses the default language's prefix here, in one place.
        // Attached when the URL generator is first built, not by building it here: a process that
        // boots the kernel before any request exists (a queue worker, a test racer) cannot build it yet.
        $stripDefaultLocale = function (UrlGenerator $url): void {
            $url->formatPathUsing(function (string $path, $route = null): string {
                return $route instanceof \Illuminate\Routing\Route && str_starts_with((string) $route->getName(), 'client.')
                    ? LocaleUrl::stripDefault($path)
                    : $path;
            });
        };
        $this->app->resolved('url') ? $stripDefaultLocale($this->app->make('url')) : $this->app->afterResolving('url', $stripDefaultLocale);

        // Resolve the effective SMTP configuration the first time anything asks for
        // the mailer, so the admin screen outranks .env for every mail in the system
        // — customer order updates, contact form, password reset, invoices, not just
        // the store-owner notification that used to configure it inline.
        //
        // Hooked on resolution rather than run here directly: this fires only when
        // mail is actually used, which keeps the settings lookup off every web
        // request and out of `migrate` on a database that has no settings table yet.
        $this->app->resolving('mail.manager', function ($manager) {
            app(MailSettings::class)->apply();

            // The MatBao relay transport; registered on resolution, like the settings
            // above, so requests that send no mail never touch the mailer.
            $manager->extend(MailSettings::GATEWAY_MAILER, fn () => new MatbaoGatewayTransport(
                app(GatewayClient::class),
            ));
        });

        $this->enforcePackageLimits();

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
            'errors.*',
            'errors::*',
        ], function ($view) {
            $view->with('siteBranding', app(SiteBranding::class)->current());
        });

        view()->composer([
            'errors.*',
            'errors::*',
        ], function ($view) {
            // An error page is rendered outside the locale middleware: read the language from
            // the URL here, against the site's own languages (a /ja page errs in Japanese, a page
            // without a prefix in the default language).
            $segment = (string) request()->segment(1);
            if ($segment !== '' && app(LanguageRegistry::class)->supports($segment)) {
                app()->setLocale($segment);
            } elseif ($segment !== 'admin') {
                app()->setLocale(LocaleUrl::split(request()->path())[0]);
            }
            $currentLocale = app()->getLocale();
            $isAdminContext = request()->is('*/admin*') || request()->is('admin*') || (request()->is('error-preview*') && request()->query('context') !== 'client');
            $adminDashboardUrl = route('admin.dashboard', ['locale' => $currentLocale]);
            // Themes name their home `client.home` under {locale}; without the parameter the
            // URL cannot be built and the error page itself throws (a blank 500 for a 404).
            $clientHomeUrl = Route::has('client.home')
                ? route('client.home', ['locale' => $currentLocale])
                : (is_file(base_path('routes/theme.php')) ? LocaleUrl::home($currentLocale) : null);

            $view->with([
                'isAdminContext' => $isAdminContext,
                'adminDashboardUrl' => $adminDashboardUrl,
                'clientHomeUrl' => $clientHomeUrl,
            ]);
        });

        // Shown to staff who can use AI; the gateway is asked at most every five minutes.
        view()->composer('admin.dashboard._ai-usage', function ($view) {
            $user = auth()->user();
            $visible = $user && $user->can('ai.use') && app(FeatureGate::class)->availableTo($user, 'ai_seo');
            $view->with(['aiUsageVisible' => $visible, 'aiUsage' => $visible ? app(AiUsage::class)->current() : null]);
        });

        // The card loads its list after the page (admin.ai.seo-tasks.summary): no gateway call here.
        view()->composer('admin.dashboard._seo-tasks', function ($view) {
            $user = auth()->user();
            $view->with([
                'seoTasksVisible' => $user && $user->can('ai.use') && app(FeatureGate::class)->availableTo($user, 'ai_seo'),
                // A connection changes shared settings, so only expose this shortcut
                // to staff who can both open and save that screen.
                'seoTasksSettingsUrl' => $user && $user->can('settings.view') && $user->can('settings.update')
                    ? route('admin.settings.index', ['tab' => 'analytics'])
                    : null,
            ]);
        });

        view()->composer('admin.shared.ai-draft-review', function ($view) {
            $post = $view->getData()['post'] ?? null;
            $view->with('aiDraftReview', $post instanceof Post ? app(PostDraftService::class)->review($post) : null);
        });

        // On the core partials, not a layout: every theme layout reaches them through
        // @include('core.head') / @include('core.editor') and needs no composer of its own.
        view()->composer(['core.partials.seo-head', 'core.partials.embed-footer'], function ($view) {
            $branding = app(SiteBranding::class)->current();
            $view->with('siteEmbeds', app(SiteEmbeds::class)->current());

            if ($view->name() !== 'core.partials.seo-head') {
                return;
            }

            $seo = $view->getData()['seo'] ?? null;

            if (! $seo instanceof SeoMeta) {
                // A page that passes no $seo still declared its title and description
                // as sections, which the layout has captured before this partial renders.
                // Inline sections arrive already escaped; the partial escapes again.
                $section = fn (string $name) => trim(html_entity_decode($view->getFactory()->yieldContent($name), ENT_QUOTES | ENT_HTML5));
                $seo = app(SeoMetaFactory::class)->fallback(
                    $section('title') ?: $branding['name'],
                    $section('meta_description') ?: null,
                );
            }

            $view->with([
                'seo' => $seo,
                'seoSiteName' => $branding['name'],
                'seoOrganization' => StructuredData::script(StructuredData::organization(
                    $branding,
                    config('app.public_frontend_url') ?: url('/'),
                )),
            ]);
        });

        view()->composer('core.partials.google-analytics', function ($view) {
            $id = null;

            try {
                // role_id marks staff (customers have none); the preview frame is staff too.
                if (auth()->user()?->role_id === null
                    && ! ClientEditorChrome::suppressed()
                    && app(FeatureGate::class)->enabled('analytics')) {
                    $id = app(AnalyticsReport::class)->settings()['measurement_id'];
                }
            } catch (\Throwable) {
                // Tracking must never break a storefront page.
            }

            $view->with('gaMeasurementId', $id);
        });

        view()->composer('admin.layouts.app', function ($view) {
            $view->with('packageLimitAlerts', app(PackageUsageAlerts::class)->forAdmin());
            // The guided tour of this page, if it has one; the header's "?" button and driver.js depend on it.
            $view->with('adminTour', AdminTours::forRoute(request()->route()?->getName()));
        });

        RateLimiter::for('public-auth', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by(self::loginThrottleKey($request)),
        ]);
        // Contact form: a burst per minute and a ceiling per day, per IP (config/contact.php).
        RateLimiter::for('public-contact', fn (Request $request) => [
            Limit::perMinute((int) config('contact.per_minute', 5))->by('contact-minute:'.$request->ip()),
            Limit::perDay((int) config('contact.per_day', 20))->by('contact-day:'.$request->ip()),
        ]);
        // Per email too: every order mails its customer_email, so checkout could otherwise flood
        // any inbox with shop-branded mail and burn the site's monthly mail quota.
        RateLimiter::for('public-checkout', fn (Request $request) => array_filter([
            Limit::perMinute(10)->by($request->ip()),
            ($emailKey = Voucher::emailKey($request->input('customer_email')))
                ? Limit::perHour(5)->by('checkout-email:'.$emailKey)
                : null,
        ]));
        // Voucher codes are short words an admin picked; without a ceiling the endpoint is a guessing oracle.
        RateLimiter::for('public-voucher', fn (Request $request) => [
            Limit::perMinute(10)->by('voucher-minute:'.$request->ip()),
            Limit::perHour(60)->by('voucher-hour:'.$request->ip()),
        ]);
        RateLimiter::for('public-read', fn (Request $request) => Limit::perMinute((int) config('app.public_read_per_minute', 300))->by('read:'.$request->ip()));
        RateLimiter::for('public-tracking', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('public-review', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by(self::loginThrottleKey($request)),
        ]);
        // Each request spends an AI credit; the gateway enforces the monthly quota itself.
        RateLimiter::for('admin-ai', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip())));

        // Uploads and imports: up to 10 × 50 MB per upload, imports run synchronously.
        RateLimiter::for('admin-heavy', fn (Request $request) => Limit::perMinute(20)->by('heavy:'.($request->user()?->id ?: $request->ip())));
        // Admin AI screens that ask the gateway on every load (job status, research, SEO tasks).
        RateLimiter::for('admin-gateway-read', fn (Request $request) => Limit::perMinute(60)->by('gw:'.($request->user()?->id ?: $request->ip())));
        RateLimiter::for('admin-translation', fn (Request $request) => [
            Limit::perMinute(20)->by((string) ($request->user()?->id ?: $request->ip())),
            Limit::perDay(500)->by((string) ($request->user()?->id ?: $request->ip())),
        ]);
        RateLimiter::for('admin-page-inline', fn (Request $request) => [
            Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip())),
        ]);
        RateLimiter::for('admin-integration', fn (Request $request) => [
            Limit::perMinute(5)->by((string) ($request->user()?->id ?: $request->ip())),
            Limit::perMinute(10)->by($request->ip()),
        ]);

        Paginator::useBootstrapFive();

        // Storefront editing components (<x-client::editable> …) are core: they live in
        // views/core/components and are updated with core. A theme may add its own
        // components in views/client/components under the same prefix.
        Blade::anonymousComponentPath(resource_path('views/core/components'), 'client');
        if (is_dir(resource_path('views/client/components'))) {
            Blade::anonymousComponentPath(resource_path('views/client/components'), 'client');
        }
        // Themes written before the editor moved to views/core still include
        // client.partials.admin-bar & co. Searched after views/, so a theme's own file wins.
        View::addLocation(resource_path('views/core/legacy'));
        // An editable CSS background on the theme's own element (App\Support\ClientBackground):
        // <section class="hero" @clientBackground('home.hero.bg', "background: url('…') center/cover")>.
        Blade::directive('clientBackground', static fn (string $arguments): string => '<?php echo app(\\App\\Support\\ClientBackground::class)->attributes('.$arguments.'); ?>');

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
        Gate::before(function ($user, $ability) {
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
            if ($user?->can('orders.view') && Schema::hasTable('orders') && app(FeatureGate::class)->enabled('catalog')) {
                $orders = Order::query()->latest()->limit(5)->get();
                foreach ($orders as $order) {
                    $notifications->push([
                        'title' => 'Đơn hàng mới #'.$order->order_number,
                        'message' => 'Khách hàng: '.$order->customer_name.'. Tổng cộng: '.number_format($order->grand_total, 0, ',', '.').' ₫.',
                        'time' => $order->created_at ? $order->created_at->diffForHumans() : '',
                        'timestamp' => $order->created_at?->getTimestamp() ?? 0,
                        'icon' => 'solar:cart-3-line-duotone',
                        'bg_color' => 'bg-primary-subtle text-primary',
                        'link' => route('admin.orders.show', $order->id),
                    ]);
                }
            }

            // Fetch recent reviews
            if ($user?->can('reviews.view') && Schema::hasTable('reviews') && app(FeatureGate::class)->enabled('review')) {
                $reviews = Review::query()->latest()->limit(5)->get();
                foreach ($reviews as $review) {
                    $prodName = 'Sản phẩm';
                    if ($review->product) {
                        $name = $review->product->name;
                        $prodName = is_array($name) ? ($name['vi'] ?? array_values($name)[0] ?? 'Sản phẩm') : $name;
                    }
                    $notifications->push([
                        'title' => 'Đánh giá mới từ '.($review->customer_name ?? 'Khách hàng'),
                        'message' => 'Đánh giá '.$review->rating.' sao cho '.$prodName,
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
            if ($user?->can('users.view') && Schema::hasTable('users')) {
                $staffAccounts = User::query()
                    ->whereNotNull('role_id')
                    ->latest()
                    ->limit(5)
                    ->get();

                // Named `$staff`, not `$user`: reusing the outer name left the
                // authenticated user pointing at the last row of this loop, which
                // would silently break the permission check of any block added after.
                foreach ($staffAccounts as $staff) {
                    $notifications->push([
                        'title' => 'Thành viên mới: '.$staff->name,
                        'message' => 'Email: '.$staff->email,
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

    /**
     * Quantity limits of the sold package, enforced where records are born.
     *
     * Model events rather than controllers: the admin form, the CSV importer and
     * the WordPress importer all create products and posts, and a limit that
     * only one of them honours is not a limit. Without a package snapshot every
     * check is a no-op.
     */
    private function enforcePackageLimits(): void
    {
        $limits = fn () => app(PackageLimits::class);

        Product::creating(fn () => $limits()->ensureCanCreate(PackageLimits::DATA_UNIT));
        Post::creating(fn () => $limits()->ensureCanCreate(PackageLimits::DATA_UNIT));

        // The monthly mail quota is enforced by the MatBao relay on the gateway,
        // where this website's code cannot reach it; PackageLimits only reads it.
    }

    /**
     * The per-account login limit, keyed like the stored email (trimmed, lowercase) so case or
     * spaces cannot dodge it. A non-string email (an array) must not crash the limiter, which
     * runs before validation.
     */
    private static function loginThrottleKey(Request $request): string
    {
        $email = $request->input('email');

        return 'login-email:'.(is_string($email) ? strtolower(trim($email)) : '');
    }
}
