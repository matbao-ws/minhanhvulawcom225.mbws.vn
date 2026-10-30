<?php

use App\Http\Middleware\EnsureActiveAdminApiUser;
use App\Http\Middleware\EnsureActiveApiUser;
use App\Http\Middleware\EnsureAiAccess;
use App\Http\Middleware\EnsureCoreUpdateAccess;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsurePackageLimit;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsSuperadmin;
use App\Http\Middleware\RedirectDefaultLocalePrefix;
use App\Http\Middleware\ResolveApiLocale;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocaleFromRoute;
use App\Http\Middleware\SyncPackageInfo;
use App\Http\Middleware\UseDefaultLocale;
use App\Http\Middleware\ValidateLocalizedAdminPath;
use App\Services\LanguageRegistry;
use App\Services\Seo\RedirectService;
use App\Support\DemoTheme;
use App\Support\ErrorReference;
use App\Support\ErrorReporting\GatewayErrorReporter;
use App\Support\PackageManifestGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// A package manifest left over from a vendor/ that has since been replaced would stop the boot
// before package:discover could rebuild it (see PackageManifestGuard).
PackageManifestGuard::clearIfStale(__DIR__.'/cache');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->get('/admin', fn (Request $request) => redirect('/'.app(LanguageRegistry::class)->adminEntryLocale(
                $request->session()->get('admin_locale') ?? $request->user()?->preferred_locale
            ).'/admin'));

            Route::middleware(['web', 'setLocale'])
                ->prefix('{locale}')
                ->where(['locale' => '[a-z]{2,3}'])
                ->group(function (): void {
                    // The default language's storefront moved to URLs without a prefix
                    // (App\Support\LocaleUrl): /vi/... answers with a permanent redirect there.
                    Route::middleware('defaultLocale.redirect')->name('client.')
                        ->group(base_path('routes/client.php'));

                    // A theme's own storefront routes (product or post detail, landing pages…).
                    // Theme-owned: a core update never ships or touches this file (CoreManifest).
                    // A demo theme on the core checkout takes its place (DemoTheme).
                    if ($themeRoutes = DemoTheme::routes()) {
                        Route::middleware('defaultLocale.redirect')->name('client.')
                            ->group($themeRoutes);
                    }

                    Route::prefix('admin')
                        ->name('admin.')
                        ->group(base_path('routes/admin.php'));
                });

            // The same storefront routes without a prefix, for the default language. Named
            // root.client.* so route('client.…') keeps generating from the prefixed ones, whose
            // default-language URLs the path formatter (AppServiceProvider) then strips.
            Route::middleware(['web', 'useDefaultLocale', 'setLocale'])
                ->name('root.')
                ->group(function (): void {
                    Route::name('client.')->group(base_path('routes/client.php'));
                    if ($themeRoutes = DemoTheme::routes()) {
                        Route::name('client.')->group($themeRoutes);
                    }
                });

            // After the storefront: a theme's home page owns /, otherwise it opens the admin. Only
            // when nothing claimed / yet: a route registered later replaces the earlier one, and
            // this redirect used to replace every theme's home with the admin login.
            if (! array_key_exists('/', Route::getRoutes()->get('GET'))) {
                Route::redirect('/', '/admin');
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trusted proxies: config/trustedproxy.php (TRUSTED_PROXIES), read per request.

        $middleware->prepend(ValidateLocalizedAdminPath::class);
        $middleware->redirectGuestsTo(function (Request $request): string {
            return route('admin.login', [
                'locale' => app(LanguageRegistry::class)->adminLocale($request->route('locale')),
            ]);
        });
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'active-api' => EnsureActiveApiUser::class,
            'active-admin-api' => EnsureActiveAdminApiUser::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'superadmin' => EnsureUserIsSuperadmin::class,
            'core-update.access' => EnsureCoreUpdateAccess::class,
            'feature' => EnsureFeatureEnabled::class,
            'ai.access' => EnsureAiAccess::class,
            'package.sync' => SyncPackageInfo::class,
            'package.limit' => EnsurePackageLimit::class,
            'setLocale' => SetLocaleFromRoute::class,
            'useDefaultLocale' => UseDefaultLocale::class,
            'defaultLocale.redirect' => RedirectDefaultLocalePrefix::class,
            'apiLocale' => ResolveApiLocale::class,
            'localize' => LaravelLocalizationRoutes::class,
            'localizationRedirect' => LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect' => LocaleSessionRedirect::class,
            'localeCookieRedirect' => LocaleCookieRedirect::class,
            'localeViewPath' => LaravelLocalizationViewPath::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep integration credentials out of flashed input if any future
        // validation path uses Laravel's automatic redirect helper.
        $exceptions->dontFlash(['cloudinary_url', 'cloud_name', 'api_key', 'api_secret']);

        // The error page shows only this code; the log line carries it next to the detail.
        $exceptions->context(fn () => ['error_ref' => ErrorReference::current()]);

        // An old URL that moved answers with its redirect (or 410); what stays a 404 is logged
        // for admin/redirects. Only here, so a page that exists costs no lookup.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => app(RedirectService::class)->handleNotFound($request));

        // The same code reaches the internal console, so it is found without this hosting's log.
        $exceptions->reportable(function (Throwable $e): void {
            app(GatewayErrorReporter::class)->capture($e);
        });
    })->create();
