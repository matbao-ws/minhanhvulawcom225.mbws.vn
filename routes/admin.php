<?php

use App\Http\Controllers\Admin\Catalog\BrandController;
use App\Http\Controllers\Admin\Catalog\CategoryController;
use App\Http\Controllers\Admin\Catalog\ProductController;
use App\Http\Controllers\Admin\Catalog\ProductVariantController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('notifications', [DashboardController::class, 'notifications'])->name('notifications.index');

    Route::get('media', [\App\Http\Controllers\Admin\MediaController::class, 'index'])->name('media.index');
    Route::post('media/upload', [\App\Http\Controllers\Admin\MediaController::class, 'upload'])->name('media.upload');
    Route::delete('media/delete', [\App\Http\Controllers\Admin\MediaController::class, 'destroy'])->name('media.delete');

    Route::middleware('superadmin')->group(function () {
        Route::get('features', [\App\Http\Controllers\Admin\FeatureController::class, 'index'])->name('features.index');
        Route::post('features', [\App\Http\Controllers\Admin\FeatureController::class, 'update'])->name('features.update');
        Route::post('features/toggle', [\App\Http\Controllers\Admin\FeatureController::class, 'toggle'])->name('features.toggle');
    });

    Route::middleware('feature:multi_admin')->group(function () {
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class)->middleware('can:manage_users');
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class)->middleware('superadmin');
    });

    Route::middleware(['feature:cms_page', 'can:manage_posts'])->group(function () {
        Route::post('post-categories/sort', [\App\Http\Controllers\Admin\PostCategoryController::class, 'sort'])->name('post-categories.sort');
        Route::put('post-categories/{post_category}/quick-update', [\App\Http\Controllers\Admin\PostCategoryController::class, 'quickUpdate'])->name('post-categories.quick-update');
        Route::resource('post-categories', \App\Http\Controllers\Admin\PostCategoryController::class)->except(['show']);
        Route::resource('posts', \App\Http\Controllers\Admin\PostController::class);
    });

    Route::middleware(['feature:voucher', 'can:manage_vouchers'])->group(function () {
        Route::resource('vouchers', \App\Http\Controllers\Admin\VoucherController::class);
    });

    Route::middleware(['feature:review', 'can:manage_reviews'])->group(function () {
        Route::get('reviews', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::put('reviews/{review}', [\App\Http\Controllers\Admin\ReviewController::class, 'update'])->name('reviews.update');
        Route::patch('reviews/{review}/toggle-visibility', [\App\Http\Controllers\Admin\ReviewController::class, 'toggleVisibility'])->name('reviews.toggle-visibility');
        Route::delete('reviews/{review}', [\App\Http\Controllers\Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
    });

    Route::middleware('can:manage_settings')->group(function () {
        Route::get('invoices', [\App\Http\Controllers\Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [\App\Http\Controllers\Admin\InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/send-email', [\App\Http\Controllers\Admin\InvoiceController::class, 'sendEmail'])->name('invoices.send-email');

        Route::get('settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');

        Route::get('notification-settings', [\App\Http\Controllers\Admin\NotificationSettingController::class, 'index'])->name('notification-settings.index');
        Route::post('notification-settings', [\App\Http\Controllers\Admin\NotificationSettingController::class, 'update'])->name('notification-settings.update');
        Route::post('notification-settings/get-zalo-chat-id', [\App\Http\Controllers\Admin\NotificationSettingController::class, 'getZaloChatId'])->name('notification-settings.get-chat-id');

        Route::post('shipping-partners/{shipping_partner}/toggle-status', [\App\Http\Controllers\Admin\ShippingPartnerController::class, 'toggleStatus'])->name('shipping-partners.toggle-status');
        Route::get('shipping-partners/{shipping_partner}/settings', [\App\Http\Controllers\Admin\ShippingPartnerController::class, 'settings'])->name('shipping-partners.settings');
        Route::post('shipping-partners/{shipping_partner}/settings', [\App\Http\Controllers\Admin\ShippingPartnerController::class, 'updateSettings'])->name('shipping-partners.update-settings');
        Route::resource('shipping-partners', \App\Http\Controllers\Admin\ShippingPartnerController::class)->except(['show']);

        Route::post('payment-methods/{payment_method}/toggle-status', [\App\Http\Controllers\Admin\PaymentMethodController::class, 'toggleStatus'])->name('payment-methods.toggle-status');
        Route::get('payment-methods/{payment_method}/settings', [\App\Http\Controllers\Admin\PaymentMethodController::class, 'settings'])->name('payment-methods.settings');
        Route::post('payment-methods/{payment_method}/settings', [\App\Http\Controllers\Admin\PaymentMethodController::class, 'updateSettings'])->name('payment-methods.update-settings');
        Route::resource('payment-methods', \App\Http\Controllers\Admin\PaymentMethodController::class)->except(['show']);

        // Addons Routes
        Route::get('addons', [\App\Http\Controllers\Admin\AddonController::class, 'index'])->name('addons.index');
        Route::get('addons/invoices', [\App\Http\Controllers\Admin\AddonController::class, 'invoices'])->name('addons.invoices');
        Route::post('addons/{addon}/checkout', [\App\Http\Controllers\Admin\AddonController::class, 'checkout'])->name('addons.checkout');
        Route::get('addons/invoices/{invoice}/status', [\App\Http\Controllers\Admin\AddonController::class, 'checkInvoiceStatus'])->name('addons.invoice-status');
        Route::get('addons/manage', [\App\Http\Controllers\Admin\AddonController::class, 'manage'])->name('addons.manage');
        Route::post('addons/{addon}/manage', [\App\Http\Controllers\Admin\AddonController::class, 'updateAddon'])->name('addons.update-addon');

        // Banners Routes
        Route::middleware('feature:banner')->group(function () {
            Route::resource('banners', \App\Http\Controllers\Admin\BannerController::class)->except(['show']);
        });
    });

    Route::middleware('feature:catalog')->group(function () {
        Route::middleware('can:manage_orders')->group(function () {
            Route::patch('orders/{order}/status', [\App\Http\Controllers\Admin\OrderController::class, 'updateStatus'])->name('orders.update-status');
            Route::post('orders/{order}/push-shipping', [\App\Http\Controllers\Admin\OrderController::class, 'pushShipping'])->name('orders.push-shipping');
            Route::resource('orders', \App\Http\Controllers\Admin\OrderController::class)->only(['index', 'show']);
        });

        Route::middleware('can:manage_products')->group(function () {
            Route::post('categories/sort', [CategoryController::class, 'sort'])->name('categories.sort');
            Route::put('categories/{category}/quick-update', [CategoryController::class, 'quickUpdate'])->name('categories.quick-update');
            Route::resource('categories', CategoryController::class)->except(['show']);

            Route::post('brands/sort', [BrandController::class, 'sort'])->name('brands.sort');
            Route::put('brands/{brand}/quick-update', [BrandController::class, 'quickUpdate'])->name('brands.quick-update');
            Route::resource('brands', BrandController::class)->except(['show']);

            Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
            Route::get('products/template/{type}', [ProductController::class, 'downloadTemplate'])->name('products.template');
            Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
            Route::resource('products', ProductController::class);
            Route::resource('products.variants', ProductVariantController::class)
                ->except(['index', 'show']);
        });
    });
});
