<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => \Mcamara\LaravelLocalization\Facades\LaravelLocalization::setLocale(),
    'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]
], function() {
    Route::get('/', function () {
        $categories = \App\Models\Category::where('is_active', true)->orderBy('sort_order')->get();
        $products = \App\Models\Product::where('is_active', true)->with('category')->get();
        return view('welcome', compact('categories', 'products'));
    });

    Route::get('/products/{slug}', function ($slug) {
        $product = \App\Models\Product::where('slug', $slug)->where('is_active', true)->with('category')->firstOrFail();
        $relatedProducts = \App\Models\Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(4)
            ->get();
        return view('product-detail', compact('product', 'relatedProducts'));
    });

    Route::get('/login', function () {
        return redirect('/admin/login');
    })->name('login');
});

Route::get('/api/docs', [\App\Http\Controllers\Api\PublicController::class, 'docs'])->name('api.docs');

Route::get('/payment/vnpay/mock', [\App\Http\Controllers\Api\PublicController::class, 'vnpayMockPayment'])->name('vnpay.mock');
Route::post('/payment/vnpay/mock/submit', [\App\Http\Controllers\Api\PublicController::class, 'vnpayMockSubmit'])->name('vnpay.mock.submit');
