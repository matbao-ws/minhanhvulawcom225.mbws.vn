<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\Catalog\BrandController;
use App\Http\Controllers\Admin\Catalog\CategoryController;
use App\Http\Controllers\Admin\Catalog\ProductController;
use App\Http\Controllers\Admin\Catalog\ProductOptionController;
use App\Http\Controllers\Admin\Catalog\ProductVariantController;
use App\Http\Controllers\Admin\ContactSubmissionController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NotificationSettingController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PostCategoryController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShippingPartnerController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:admin-login')->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::post('/impersonate/leave', [UserController::class, 'leaveImpersonate'])
    ->middleware('auth')
    ->name('users.impersonate.leave');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('notifications', [DashboardController::class, 'notifications'])->name('notifications.index');
    Route::get('search', [SearchController::class, 'search'])->name('search');
    Route::post('translations/preview', [TranslationController::class, 'preview'])
        ->middleware(['can:translate_content', 'throttle:admin-translation'])
        ->name('translations.preview');

    Route::middleware('can:manage_media')->group(function () {
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/resources', [MediaController::class, 'resources'])->name('media.resources');
        Route::post('media/upload', [MediaController::class, 'upload'])->name('media.upload');
        Route::delete('media/delete', [MediaController::class, 'destroy'])->name('media.delete');
    });

    Route::middleware('superadmin')->group(function () {
        Route::get('features', [FeatureController::class, 'index'])->name('features.index');
        Route::post('features', [FeatureController::class, 'update'])->name('features.update');
        Route::post('features/toggle', [FeatureController::class, 'toggle'])->name('features.toggle');
        Route::post('features/group-toggle', [FeatureController::class, 'toggleGroup'])->name('features.group-toggle');
        Route::get('languages', [LanguageController::class, 'index'])->name('languages.index');
        Route::post('languages', [LanguageController::class, 'store'])->name('languages.store');
        Route::put('languages/preferences', [LanguageController::class, 'updatePreferences'])->name('languages.preferences');
        Route::put('languages/{language}', [LanguageController::class, 'update'])->name('languages.update');
    });

    Route::middleware('feature:multi_admin')->group(function () {
        Route::resource('users', UserController::class)->middleware('can:manage_users');
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
            ->name('users.toggle-status')
            ->middleware('can:manage_users');
        Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])
            ->name('users.impersonate')
            ->middleware('superadmin');
        Route::resource('roles', RoleController::class)->middleware('superadmin');
    });

    Route::middleware(['feature:cms_page', 'can:manage_posts'])->group(function () {
        Route::patch('posts/bulk', [PostController::class, 'bulk'])->name('posts.bulk');
        Route::post('posts/import-wordpress', [PostController::class, 'importWordPress'])->name('posts.import-wordpress');
        Route::post('post-categories/sort', [PostCategoryController::class, 'sort'])->name('post-categories.sort');
        Route::put('post-categories/{post_category}/quick-update', [PostCategoryController::class, 'quickUpdate'])->name('post-categories.quick-update');
        Route::resource('post-categories', PostCategoryController::class)->except(['show']);
        Route::resource('posts', PostController::class);
    });

    Route::middleware(['feature:cms_page', 'can:manage_pages'])->group(function () {
        Route::get('pages/{page}/preview', [PageController::class, 'preview'])->name('pages.preview');
        Route::patch('pages/{page}/inline', [PageController::class, 'inlineUpdate'])->middleware('throttle:admin-page-inline')->name('pages.inline-update');
        Route::post('pages/{page}/revisions/{revision}/restore', [PageController::class, 'restore'])->name('pages.revisions.restore');
        Route::resource('pages', PageController::class)->except(['show']);
    });

    Route::middleware(['feature:voucher', 'can:manage_vouchers'])->group(function () {
        Route::patch('vouchers/bulk', [VoucherController::class, 'bulk'])->name('vouchers.bulk');
        Route::resource('vouchers', VoucherController::class);
    });

    Route::middleware(['feature:catalog', 'can:manage_vouchers'])->group(function () {
        Route::resource('promotions', PromotionController::class)->except(['show']);
    });

    Route::middleware(['feature:review', 'can:manage_reviews'])->group(function () {
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::put('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
        Route::patch('reviews/{review}/toggle-visibility', [ReviewController::class, 'toggleVisibility'])->name('reviews.toggle-visibility');
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    });

    Route::middleware('can:manage_contacts')->group(function () {
        Route::get('contact-submissions', [ContactSubmissionController::class, 'index'])->name('contact-submissions.index');
        Route::patch('contact-submissions/{contactSubmission}/toggle-read', [ContactSubmissionController::class, 'toggleRead'])->name('contact-submissions.toggle-read');
        Route::delete('contact-submissions/{contactSubmission}', [ContactSubmissionController::class, 'destroy'])->name('contact-submissions.destroy');
    });

    Route::middleware('can:manage_settings')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail'])->name('invoices.send-email');

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('notification-settings', [NotificationSettingController::class, 'index'])->name('notification-settings.index');
        Route::post('notification-settings', [NotificationSettingController::class, 'update'])->name('notification-settings.update');
        Route::post('notification-settings/get-zalo-chat-id', [NotificationSettingController::class, 'getZaloChatId'])->name('notification-settings.get-chat-id');

        Route::middleware('feature:shipping')->group(function () {
            Route::post('shipping-partners/{shipping_partner}/toggle-status', [ShippingPartnerController::class, 'toggleStatus'])->name('shipping-partners.toggle-status');
            Route::get('shipping-partners/{shipping_partner}/settings', [ShippingPartnerController::class, 'settings'])->name('shipping-partners.settings');
            Route::post('shipping-partners/{shipping_partner}/settings', [ShippingPartnerController::class, 'updateSettings'])->name('shipping-partners.update-settings');
            Route::resource('shipping-partners', ShippingPartnerController::class)->except(['show']);
        });

        // Either payment feature makes this screen meaningful; the controller
        // still decides which individual methods may be listed or edited.
        Route::middleware('feature:cod_order,online_payment')->group(function () {
            Route::post('payment-methods/{payment_method}/toggle-status', [PaymentMethodController::class, 'toggleStatus'])->name('payment-methods.toggle-status');
            Route::get('payment-methods/{payment_method}/settings', [PaymentMethodController::class, 'settings'])->name('payment-methods.settings');
            Route::post('payment-methods/{payment_method}/settings', [PaymentMethodController::class, 'updateSettings'])->name('payment-methods.update-settings');
            Route::resource('payment-methods', PaymentMethodController::class)->except(['show']);
        });

        // Integration catalogue. Activation is handled by support; no in-app addon sales.
        Route::get('addons', [AddonController::class, 'index'])->name('addons.index');

    });

    Route::middleware(['feature:banner', 'can:manage_banners'])->group(function () {
        Route::patch('banners/bulk', [BannerController::class, 'bulk'])->name('banners.bulk');
        Route::resource('banners', BannerController::class)->except(['show']);
    });

    Route::middleware('can:view_audit_log')->group(function () {
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

    Route::middleware('can:view_customers')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/profile', [CustomerController::class, 'show'])->name('customers.show');
    });

    Route::middleware('feature:catalog')->group(function () {
        Route::middleware('can:manage_orders')->group(function () {
            Route::get('orders/customer-suggestions', [OrderController::class, 'customerSuggestions'])->name('orders.customer-suggestions');
            Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
            // Pushing a waybill drives the carrier integration configured on the
            // shipping screen, so it follows the same switch.
            Route::post('orders/{order}/push-shipping', [OrderController::class, 'pushShipping'])
                ->middleware('feature:shipping')
                ->name('orders.push-shipping');
            Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');
            Route::resource('orders', OrderController::class)->only(['index', 'show', 'create', 'store']);
        });

        Route::middleware('can:manage_products')->group(function () {
            Route::patch('categories/bulk', [CategoryController::class, 'bulk'])->name('categories.bulk');
            Route::post('categories/sort', [CategoryController::class, 'sort'])->name('categories.sort');
            Route::put('categories/{category}/quick-update', [CategoryController::class, 'quickUpdate'])->name('categories.quick-update');
            Route::resource('categories', CategoryController::class)->except(['show']);

            Route::patch('brands/bulk', [BrandController::class, 'bulk'])->name('brands.bulk');
            Route::post('brands/sort', [BrandController::class, 'sort'])->name('brands.sort');
            Route::put('brands/{brand}/quick-update', [BrandController::class, 'quickUpdate'])->name('brands.quick-update');
            Route::resource('brands', BrandController::class)->except(['show']);

            Route::patch('products/bulk', [ProductController::class, 'bulk'])->name('products.bulk');
            Route::post('products/sort', [ProductController::class, 'sort'])->name('products.sort');
            Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
            Route::get('products/template/{type}', [ProductController::class, 'downloadTemplate'])->name('products.template');
            Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
            Route::resource('products', ProductController::class);
            Route::get('products/{product}/options', [ProductOptionController::class, 'edit'])->name('products.options.edit');
            Route::put('products/{product}/options', [ProductOptionController::class, 'update'])->name('products.options.update');
            Route::post('products/{product}/variants/generate', [ProductVariantController::class, 'generate'])->name('products.variants.generate');
            Route::resource('products.variants', ProductVariantController::class)
                ->except(['index', 'show']);
        });
    });
});
