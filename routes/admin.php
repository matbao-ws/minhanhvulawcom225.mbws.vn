<?php

use App\Http\Controllers\Admin\ActivityLogController;
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
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
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
use App\Http\Controllers\Admin\SiteBlockController;
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
        ->middleware(['can:translations.use', 'throttle:admin-translation'])
        ->name('translations.preview');

    Route::middleware('can:media.view')->group(function () {
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/resources', [MediaController::class, 'resources'])->name('media.resources');
    });
    Route::post('media/upload', [MediaController::class, 'upload'])->middleware('can:media.create')->name('media.upload');
    Route::delete('media/delete', [MediaController::class, 'destroy'])->middleware('can:media.delete')->name('media.delete');

    // Feature flags and impersonation stay superadmin-only: they can hand out
    // access rather than merely use it.
    Route::middleware('superadmin')->group(function () {
        Route::get('features', [FeatureController::class, 'index'])->name('features.index');
        Route::post('features', [FeatureController::class, 'update'])->name('features.update');
        Route::post('features/toggle', [FeatureController::class, 'toggle'])->name('features.toggle');
        Route::post('features/group-toggle', [FeatureController::class, 'toggleGroup'])->name('features.group-toggle');
    });

    Route::get('languages', [LanguageController::class, 'index'])->middleware('can:languages.view')->name('languages.index');
    Route::post('languages', [LanguageController::class, 'store'])->middleware('can:languages.create')->name('languages.store');
    Route::put('languages/preferences', [LanguageController::class, 'updatePreferences'])->middleware('can:languages.update')->name('languages.preferences');
    Route::put('languages/{language}', [LanguageController::class, 'update'])->middleware('can:languages.update')->name('languages.update');

    Route::middleware('feature:multi_admin')->group(function () {
        // Literal segments are registered before `{user}` so /users/create is
        // not swallowed by the show route.
        Route::resource('users', UserController::class)->only(['create', 'store'])->middleware('can:users.create');
        Route::resource('users', UserController::class)->only(['index', 'show'])->middleware('can:users.view');
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
            ->name('users.toggle-status')
            ->middleware('can:users.update');
        Route::resource('users', UserController::class)->only(['edit', 'update'])->middleware('can:users.update');
        Route::resource('users', UserController::class)->only(['destroy'])->middleware('can:users.delete');
        Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])
            ->name('users.impersonate')
            ->middleware('superadmin');

        Route::resource('roles', RoleController::class)->only(['create', 'store'])->middleware('can:roles.create');
        Route::resource('roles', RoleController::class)->only(['index'])->middleware('can:roles.view');
        Route::resource('roles', RoleController::class)->only(['edit', 'update'])->middleware('can:roles.update');
        Route::resource('roles', RoleController::class)->only(['destroy'])->middleware('can:roles.delete');
    });

    Route::middleware('feature:cms_page')->group(function () {
        Route::post('posts/import-wordpress', [PostController::class, 'importWordPress'])->middleware('can:posts.create')->name('posts.import-wordpress');
        Route::patch('posts/bulk', [PostController::class, 'bulk'])->middleware('can:posts.update')->name('posts.bulk');
        Route::post('post-categories/sort', [PostCategoryController::class, 'sort'])->middleware('can:posts.update')->name('post-categories.sort');
        Route::put('post-categories/{post_category}/quick-update', [PostCategoryController::class, 'quickUpdate'])->middleware('can:posts.update')->name('post-categories.quick-update');

        Route::resource('post-categories', PostCategoryController::class)->only(['create', 'store'])->middleware('can:posts.create');
        Route::resource('post-categories', PostCategoryController::class)->only(['index'])->middleware('can:posts.view');
        Route::resource('post-categories', PostCategoryController::class)->only(['edit', 'update'])->middleware('can:posts.update');
        Route::resource('post-categories', PostCategoryController::class)->only(['destroy'])->middleware('can:posts.delete');

        Route::resource('posts', PostController::class)->only(['create', 'store'])->middleware('can:posts.create');
        Route::resource('posts', PostController::class)->only(['index', 'show'])->middleware('can:posts.view');
        Route::resource('posts', PostController::class)->only(['edit', 'update'])->middleware('can:posts.update');
        Route::resource('posts', PostController::class)->only(['destroy'])->middleware('can:posts.delete');
    });

    // Inline edits to static storefront regions. Same permission and throttle
    // as inline page editing: it is the same power over the same site.
    Route::middleware(['can:pages.update', 'throttle:admin-page-inline'])->group(function () {
        Route::patch('site-blocks', [SiteBlockController::class, 'update'])->name('site-blocks.update');
        Route::delete('site-blocks', [SiteBlockController::class, 'restore'])->name('site-blocks.restore');
        // How many boxes a repeatable region holds. Same gate: adding a box is
        // editing the page, and the template still owns what the box looks like.
        Route::post('site-lists/items', [SiteBlockController::class, 'addListItem'])->name('site-lists.items.store');
        Route::delete('site-lists/items', [SiteBlockController::class, 'removeListItem'])->name('site-lists.items.destroy');
        Route::patch('site-lists/items/order', [SiteBlockController::class, 'reorderListItems'])->name('site-lists.items.reorder');
    });

    Route::middleware('feature:cms_page')->group(function () {
        Route::get('pages/{page}/preview', [PageController::class, 'preview'])->middleware('can:pages.view')->name('pages.preview');
        Route::patch('pages/{page}/inline', [PageController::class, 'inlineUpdate'])->middleware(['can:pages.update', 'throttle:admin-page-inline'])->name('pages.inline-update');
        Route::post('pages/{page}/revisions/{revision}/restore', [PageController::class, 'restore'])->middleware('can:pages.update')->name('pages.revisions.restore');

        Route::resource('pages', PageController::class)->only(['create', 'store'])->middleware('can:pages.create');
        Route::resource('pages', PageController::class)->only(['index'])->middleware('can:pages.view');
        Route::resource('pages', PageController::class)->only(['edit', 'update'])->middleware('can:pages.update');
        Route::resource('pages', PageController::class)->only(['destroy'])->middleware('can:pages.delete');
    });

    Route::middleware('feature:voucher')->group(function () {
        Route::patch('vouchers/bulk', [VoucherController::class, 'bulk'])->middleware('can:vouchers.update')->name('vouchers.bulk');
        Route::resource('vouchers', VoucherController::class)->only(['create', 'store'])->middleware('can:vouchers.create');
        Route::resource('vouchers', VoucherController::class)->only(['index', 'show'])->middleware('can:vouchers.view');
        Route::resource('vouchers', VoucherController::class)->only(['edit', 'update'])->middleware('can:vouchers.update');
        Route::resource('vouchers', VoucherController::class)->only(['destroy'])->middleware('can:vouchers.delete');
    });

    Route::middleware('feature:catalog')->group(function () {
        Route::resource('promotions', PromotionController::class)->only(['create', 'store'])->middleware('can:promotions.create');
        Route::resource('promotions', PromotionController::class)->only(['index'])->middleware('can:promotions.view');
        Route::resource('promotions', PromotionController::class)->only(['edit', 'update'])->middleware('can:promotions.update');
        Route::resource('promotions', PromotionController::class)->only(['destroy'])->middleware('can:promotions.delete');
    });

    Route::middleware('feature:review')->group(function () {
        Route::get('reviews', [ReviewController::class, 'index'])->middleware('can:reviews.view')->name('reviews.index');
        Route::put('reviews/{review}', [ReviewController::class, 'update'])->middleware('can:reviews.update')->name('reviews.update');
        Route::patch('reviews/{review}/toggle-visibility', [ReviewController::class, 'toggleVisibility'])->middleware('can:reviews.update')->name('reviews.toggle-visibility');
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->middleware('can:reviews.delete')->name('reviews.destroy');
    });

    Route::get('contact-submissions', [ContactSubmissionController::class, 'index'])->middleware('can:contacts.view')->name('contact-submissions.index');
    Route::patch('contact-submissions/{contactSubmission}/toggle-read', [ContactSubmissionController::class, 'toggleRead'])->middleware('can:contacts.update')->name('contact-submissions.toggle-read');
    Route::delete('contact-submissions/{contactSubmission}', [ContactSubmissionController::class, 'destroy'])->middleware('can:contacts.delete')->name('contact-submissions.destroy');

    Route::get('settings', [SettingController::class, 'index'])->middleware('can:settings.view')->name('settings.index');
    Route::post('settings', [SettingController::class, 'update'])->middleware('can:settings.update')->name('settings.update');

    Route::get('notification-settings', [NotificationSettingController::class, 'index'])->middleware('can:settings.view')->name('notification-settings.index');
    Route::post('notification-settings', [NotificationSettingController::class, 'update'])->middleware('can:settings.update')->name('notification-settings.update');
    Route::post('notification-settings/get-zalo-chat-id', [NotificationSettingController::class, 'getZaloChatId'])->middleware('can:settings.update')->name('notification-settings.get-chat-id');

    Route::middleware('feature:shipping')->group(function () {
        Route::post('shipping-partners/{shipping_partner}/toggle-status', [ShippingPartnerController::class, 'toggleStatus'])->middleware('can:shipping.update')->name('shipping-partners.toggle-status');
        Route::get('shipping-partners/{shipping_partner}/settings', [ShippingPartnerController::class, 'settings'])->middleware('can:shipping.view')->name('shipping-partners.settings');
        Route::post('shipping-partners/{shipping_partner}/settings', [ShippingPartnerController::class, 'updateSettings'])->middleware('can:shipping.update')->name('shipping-partners.update-settings');

        Route::resource('shipping-partners', ShippingPartnerController::class)->only(['create', 'store'])->middleware('can:shipping.create');
        Route::resource('shipping-partners', ShippingPartnerController::class)->only(['index'])->middleware('can:shipping.view');
        Route::resource('shipping-partners', ShippingPartnerController::class)->only(['edit', 'update'])->middleware('can:shipping.update');
        Route::resource('shipping-partners', ShippingPartnerController::class)->only(['destroy'])->middleware('can:shipping.delete');
    });

    // Either payment feature makes this screen meaningful; the controller
    // still decides which individual methods may be listed or edited.
    Route::middleware('feature:cod_order,online_payment')->group(function () {
        Route::post('payment-methods/{payment_method}/toggle-status', [PaymentMethodController::class, 'toggleStatus'])->middleware('can:payments.update')->name('payment-methods.toggle-status');
        Route::get('payment-methods/{payment_method}/settings', [PaymentMethodController::class, 'settings'])->middleware('can:payments.view')->name('payment-methods.settings');
        Route::post('payment-methods/{payment_method}/settings', [PaymentMethodController::class, 'updateSettings'])->middleware('can:payments.update')->name('payment-methods.update-settings');

        Route::resource('payment-methods', PaymentMethodController::class)->only(['create', 'store'])->middleware('can:payments.create');
        Route::resource('payment-methods', PaymentMethodController::class)->only(['index'])->middleware('can:payments.view');
        Route::resource('payment-methods', PaymentMethodController::class)->only(['edit', 'update'])->middleware('can:payments.update');
        Route::resource('payment-methods', PaymentMethodController::class)->only(['destroy'])->middleware('can:payments.delete');
    });

    Route::middleware('feature:menu')->group(function () {
        // Item routes come first: `menus/create` would otherwise be captured by
        // `menus/{menu}` once the menu resource registers its show-shaped paths.
        Route::post('menus/{menu}/items/sort', [MenuItemController::class, 'sort'])->middleware('can:menus.update')->name('menus.items.sort');
        Route::get('menus/{menu}/items/create', [MenuItemController::class, 'create'])->middleware('can:menus.update')->name('menus.items.create');
        Route::post('menus/{menu}/items', [MenuItemController::class, 'store'])->middleware('can:menus.update')->name('menus.items.store');
        Route::get('menus/{menu}/items', [MenuItemController::class, 'index'])->middleware('can:menus.view')->name('menus.items.index');
        Route::get('menus/{menu}/items/{item}/edit', [MenuItemController::class, 'edit'])->middleware('can:menus.update')->name('menus.items.edit');
        Route::put('menus/{menu}/items/{item}', [MenuItemController::class, 'update'])->middleware('can:menus.update')->name('menus.items.update');
        Route::delete('menus/{menu}/items/{item}', [MenuItemController::class, 'destroy'])->middleware('can:menus.delete')->name('menus.items.destroy');

        Route::resource('menus', MenuController::class)->only(['create', 'store'])->middleware('can:menus.create');
        Route::resource('menus', MenuController::class)->only(['index'])->middleware('can:menus.view');
        Route::resource('menus', MenuController::class)->only(['edit', 'update'])->middleware('can:menus.update');
        Route::resource('menus', MenuController::class)->only(['destroy'])->middleware('can:menus.delete');
    });

    Route::middleware('feature:banner')->group(function () {
        Route::patch('banners/bulk', [BannerController::class, 'bulk'])->middleware('can:banners.update')->name('banners.bulk');
        Route::resource('banners', BannerController::class)->only(['create', 'store'])->middleware('can:banners.create');
        Route::resource('banners', BannerController::class)->only(['index'])->middleware('can:banners.view');
        Route::resource('banners', BannerController::class)->only(['edit', 'update'])->middleware('can:banners.update');
        Route::resource('banners', BannerController::class)->only(['destroy'])->middleware('can:banners.delete');
    });

    Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('can:activity_logs.view')->name('activity-logs.index');

    Route::middleware('can:customers.view')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        // Literal segment registered alongside the others; there is no {customer}
        // route to shadow because customers are keyed by email, not by id.
        Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::get('customers/profile', [CustomerController::class, 'show'])->name('customers.show');
    });

    Route::middleware('feature:catalog')->group(function () {
        Route::get('orders/customer-suggestions', [OrderController::class, 'customerSuggestions'])->middleware('can:orders.create')->name('orders.customer-suggestions');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('can:orders.update')->name('orders.update-status');
        // Pushing a waybill drives the carrier integration configured on the
        // shipping screen, so it follows the same switch.
        Route::post('orders/{order}/push-shipping', [OrderController::class, 'pushShipping'])
            ->middleware(['can:orders.update', 'feature:shipping'])
            ->name('orders.push-shipping');
        Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->middleware('can:orders.update')->name('orders.refund');
        Route::resource('orders', OrderController::class)->only(['create', 'store'])->middleware('can:orders.create');
        Route::resource('orders', OrderController::class)->only(['index', 'show'])->middleware('can:orders.view');

        Route::patch('categories/bulk', [CategoryController::class, 'bulk'])->middleware('can:products.update')->name('categories.bulk');
        Route::post('categories/sort', [CategoryController::class, 'sort'])->middleware('can:products.update')->name('categories.sort');
        Route::put('categories/{category}/quick-update', [CategoryController::class, 'quickUpdate'])->middleware('can:products.update')->name('categories.quick-update');
        Route::resource('categories', CategoryController::class)->only(['create', 'store'])->middleware('can:products.create');
        Route::resource('categories', CategoryController::class)->only(['index'])->middleware('can:products.view');
        Route::resource('categories', CategoryController::class)->only(['edit', 'update'])->middleware('can:products.update');
        Route::resource('categories', CategoryController::class)->only(['destroy'])->middleware('can:products.delete');

        Route::patch('brands/bulk', [BrandController::class, 'bulk'])->middleware('can:products.update')->name('brands.bulk');
        Route::post('brands/sort', [BrandController::class, 'sort'])->middleware('can:products.update')->name('brands.sort');
        Route::put('brands/{brand}/quick-update', [BrandController::class, 'quickUpdate'])->middleware('can:products.update')->name('brands.quick-update');
        Route::resource('brands', BrandController::class)->only(['create', 'store'])->middleware('can:products.create');
        Route::resource('brands', BrandController::class)->only(['index'])->middleware('can:products.view');
        Route::resource('brands', BrandController::class)->only(['edit', 'update'])->middleware('can:products.update');
        Route::resource('brands', BrandController::class)->only(['destroy'])->middleware('can:products.delete');

        Route::patch('products/bulk', [ProductController::class, 'bulk'])->middleware('can:products.update')->name('products.bulk');
        Route::post('products/sort', [ProductController::class, 'sort'])->middleware('can:products.update')->name('products.sort');
        Route::get('products/export', [ProductController::class, 'export'])->middleware('can:products.view')->name('products.export');
        Route::get('products/template/{type}', [ProductController::class, 'downloadTemplate'])->middleware('can:products.view')->name('products.template');
        Route::post('products/import', [ProductController::class, 'import'])->middleware('can:products.create')->name('products.import');
        Route::resource('products', ProductController::class)->only(['create', 'store'])->middleware('can:products.create');
        Route::resource('products', ProductController::class)->only(['index', 'show'])->middleware('can:products.view');
        Route::resource('products', ProductController::class)->only(['edit', 'update'])->middleware('can:products.update');
        Route::resource('products', ProductController::class)->only(['destroy'])->middleware('can:products.delete');

        Route::get('products/{product}/options', [ProductOptionController::class, 'edit'])->middleware('can:products.update')->name('products.options.edit');
        Route::put('products/{product}/options', [ProductOptionController::class, 'update'])->middleware('can:products.update')->name('products.options.update');
        Route::post('products/{product}/variants/generate', [ProductVariantController::class, 'generate'])->middleware('can:products.create')->name('products.variants.generate');
        Route::resource('products.variants', ProductVariantController::class)->only(['create', 'store'])->middleware('can:products.create');
        Route::resource('products.variants', ProductVariantController::class)->only(['edit', 'update'])->middleware('can:products.update');
        Route::resource('products.variants', ProductVariantController::class)->only(['destroy'])->middleware('can:products.delete');
    });
});
