<?php

use App\Http\Middleware\EnsureActiveAdminApiUser;
use App\Http\Middleware\EnsureActiveApiUser;
use App\Http\Middleware\EnsureCoreUpdateAccess;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsurePackageLimit;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsSuperadmin;
use App\Http\Middleware\ResolveApiLocale;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocaleFromRoute;
use App\Http\Middleware\SyncPackageInfo;
use App\Http\Middleware\ValidateLocalizedAdminPath;
use App\Services\LanguageRegistry;
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
            Route::get('/admin', fn () => redirect('/'.app(LanguageRegistry::class)->adminLocale(null).'/admin'));

            Route::middleware(['web', 'setLocale'])
                ->prefix('{locale}')
                ->where(['locale' => '[a-z]{2,3}'])
                ->group(function (): void {
                    Route::name('client.')
                        ->group(base_path('routes/client.php'));

                    // A theme's own storefront routes (product or post detail, landing pages…).
                    // Theme-owned: a core update never ships or touches this file (CoreManifest).
                    if (is_file(base_path('routes/theme.php'))) {
                        Route::name('client.')
                            ->group(base_path('routes/theme.php'));
                    }

                    Route::prefix('admin')
                        ->name('admin.')
                        ->group(base_path('routes/admin.php'));
                });
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
            'package.sync' => SyncPackageInfo::class,
            'package.limit' => EnsurePackageLimit::class,
            'setLocale' => SetLocaleFromRoute::class,
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

        // The same code reaches the internal console, so it is found without this hosting's log.
        $exceptions->reportable(function (Throwable $e): void {
            app(GatewayErrorReporter::class)->capture($e);
        });
    })->create();
