<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::get('/login', fn () => redirect('/'.app(\App\Services\LanguageRegistry::class)->defaultLocale().'/admin/login'))->name('login');

Route::get('/api/docs', [\App\Http\Controllers\Api\PublicController::class, 'docs'])->name('api.docs');

// Dynamic so they follow APP_URL and the environment (staging is never indexed).
// There must be no robots.txt / sitemap.xml file in public/: the web server
// would serve it before Laravel is asked.
Route::get('/robots.txt', [\App\Http\Controllers\Client\SeoFileController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [\App\Http\Controllers\Client\SeoFileController::class, 'sitemap'])
    ->middleware('throttle:60,1')
    ->name('seo.sitemap');

Route::get('/customer/reset-password/{token}', [\App\Http\Controllers\Auth\CustomerResetPasswordController::class, 'create'])->name('customer.password.reset');
Route::post('/customer/reset-password', [\App\Http\Controllers\Auth\CustomerResetPasswordController::class, 'store'])
    ->middleware('throttle:public-auth')
    ->name('customer.password.update');

if (config('app.payment_mock_enabled') && app()->environment(['local', 'testing'])) {
    Route::get('/payment/vnpay/mock', [\App\Http\Controllers\Api\PublicController::class, 'vnpayMockPayment'])->name('vnpay.mock');
    Route::post('/payment/vnpay/mock/submit', [\App\Http\Controllers\Api\PublicController::class, 'vnpayMockSubmit'])
        ->middleware('throttle:10,1')
        ->name('vnpay.mock.submit');
}
