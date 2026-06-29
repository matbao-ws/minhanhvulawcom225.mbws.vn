<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/login', '/'.config('app.locale', 'vi').'/admin/login')->name('login');

Route::get('/api/docs', [\App\Http\Controllers\Api\PublicController::class, 'docs'])->name('api.docs');

Route::get('/payment/vnpay/mock', [\App\Http\Controllers\Api\PublicController::class, 'vnpayMockPayment'])->name('vnpay.mock');
Route::post('/payment/vnpay/mock/submit', [\App\Http\Controllers\Api\PublicController::class, 'vnpayMockSubmit'])->name('vnpay.mock.submit');
