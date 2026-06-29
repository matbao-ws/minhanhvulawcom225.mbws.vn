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
            Route::redirect('/admin', '/'.config('app.locale', 'vi').'/admin');

            Route::middleware(['web', 'setLocale'])
                ->prefix('{locale}')
                ->whereIn('locale', array_keys(config('laravellocalization.supportedLocales', [])))
                ->group(function (): void {
                    Route::prefix('admin')
                        ->name('admin.')
                        ->group(base_path('routes/admin.php'));
                });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'superadmin' => \App\Http\Middleware\EnsureUserIsSuperadmin::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            'setLocale' => \App\Http\Middleware\SetLocaleFromRoute::class,
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
