<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/admin', fn () => redirect('/'.app(\App\Services\LanguageRegistry::class)->defaultLocale().'/admin'));

            Route::middleware(['web', 'setLocale'])
                ->prefix('{locale}')
                ->where(['locale' => '[a-z]{2,3}'])
                ->group(function (): void {
                    Route::name('client.')
                        ->group(base_path('routes/client.php'));

                    Route::prefix('admin')
                        ->name('admin.')
                        ->group(base_path('routes/admin.php'));
                });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a TLS-terminating proxy the app would otherwise see plain HTTP
        // and generate http:// asset URLs, which browsers block as mixed
        // content. Opt in per environment via TRUSTED_PROXIES ("*" for a
        // trusted load balancer, or a comma-separated list of addresses).
        $proxies = trim((string) env('TRUSTED_PROXIES', ''));
        if ($proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->prepend(\App\Http\Middleware\ValidateLocalizedAdminPath::class);
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request): string {
            return route('admin.login', [
                'locale' => $request->route('locale') ?: app(\App\Services\LanguageRegistry::class)->defaultLocale(),
            ]);
        });
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'active-api' => \App\Http\Middleware\EnsureActiveApiUser::class,
            'active-admin-api' => \App\Http\Middleware\EnsureActiveAdminApiUser::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'superadmin' => \App\Http\Middleware\EnsureUserIsSuperadmin::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            'setLocale' => \App\Http\Middleware\SetLocaleFromRoute::class,
            'apiLocale' => \App\Http\Middleware\ResolveApiLocale::class,
            'localize' => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes::class,
            'localizationRedirect' => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect' => \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            'localeCookieRedirect' => \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            'localeViewPath' => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
