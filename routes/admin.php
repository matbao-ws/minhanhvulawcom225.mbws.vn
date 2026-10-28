<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AiBulkController;
use App\Http\Controllers\Admin\AiContentController;
use App\Http\Controllers\Admin\AiMetaController;
use App\Http\Controllers\Admin\AiPostDraftController;
use App\Http\Controllers\Admin\AiResearchController;
use App\Http\Controllers\Admin\AiRewriteController;
use App\Http\Controllers\Admin\AiSeoTasksController;
use App\Http\Controllers\Admin\AiToolsController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\Catalog\BrandController;
use App\Http\Controllers\Admin\Catalog\CategoryController;
use App\Http\Controllers\Admin\Catalog\ProductController;
use App\Http\Controllers\Admin\Catalog\ProductOptionController;
use App\Http\Controllers\Admin\Catalog\ProductVariantController;
use App\Http\Controllers\Admin\CheckoutTestController;
use App\Http\Controllers\Admin\ContactSubmissionController;
use App\Http\Controllers\Admin\CoreUpdateController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExtensionController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\GhnLookupController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\NotificationSettingController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\PartnerGroupController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PostCategoryController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShippingPartnerController;
use App\Http\Controllers\Admin\SiteBlockController;
use App\Http\Controllers\Admin\SiteSectionController;
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
Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:admin-login')->name('password.update');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::post('/impersonate/leave', [UserController::class, 'leaveImpersonate'])
    ->middleware('auth')
    ->name('users.impersonate.leave');

Route::middleware(['auth', 'admin', 'package.sync'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);
    // Place a test order through the public checkout API; look up GHN address codes.
    Route::get('checkout-test', CheckoutTestController::class)
        ->middleware('can:orders.create')
        ->name('checkout.test');
    // GHN connection check and address lookups (order "Tạo vận đơn" dialog, checkout test page).
    Route::middleware(['can:orders.view', 'feature:shipping', 'throttle:60,1'])->prefix('shipping/ghn')->name('shipping.ghn.')->group(function () {
        Route::get('status', [GhnLookupController::class, 'status'])->name('status');
        Route::get('provinces', [GhnLookupController::class, 'provinces'])->name('provinces');
        Route::get('provinces/{provinceId}/districts', [GhnLookupController::class, 'districts'])->whereNumber('provinceId')->name('districts');
        Route::get('districts/{districtId}/wards', [GhnLookupController::class, 'wards'])->whereNumber('districtId')->name('wards');
    });
    // Each click may call the internal tool; keep it to a handful per minute.
    Route::post('dashboard/package-usage/refresh', [DashboardController::class, 'refreshPackageUsage'])
        ->middleware('throttle:6,1')
        ->name('dashboard.package-usage.refresh');
    // Google Analytics: loaded after the dashboard renders; each call may reach Google.
    Route::middleware(['can:analytics.view', 'feature:analytics'])->group(function () {
        Route::get('dashboard/analytics', [AnalyticsController::class, 'show'])
            ->middleware('throttle:30,1')->name('dashboard.analytics');
        Route::post('dashboard/analytics/refresh', [AnalyticsController::class, 'refresh'])
            ->middleware('throttle:6,1')->name('dashboard.analytics.refresh');
    });
    Route::get('notifications', [DashboardController::class, 'notifications'])->name('notifications.index');
    Route::get('search', [SearchController::class, 'search'])->name('search');
    Route::post('translations/preview', [TranslationController::class, 'preview'])
        ->middleware(['feature:multilingual', 'can:translations.use', 'throttle:admin-translation'])
        ->name('translations.preview');

    // AI SEO Copilot. ai.access: the plan has it, it is on, the role has ai.use (App\Support\AiAccess;
    // the buttons are always shown, a locked one is stopped here with the reason). ai.apply writes
    // suggestions, and applying also checks the record's own update permission.
    Route::middleware(['ai.access', 'can:ai.use'])->prefix('ai')->name('ai.')->group(function () {
        Route::post('meta-suggestions', [AiMetaController::class, 'suggest'])
            ->middleware('throttle:admin-ai')->name('meta.suggest');
        Route::post('meta-suggestions/{suggestion}/apply', [AiMetaController::class, 'apply'])
            ->whereNumber('suggestion')->middleware('can:ai.apply')->name('meta.apply');
        Route::post('meta-suggestions/{suggestion}/reject', [AiMetaController::class, 'reject'])
            ->whereNumber('suggestion')->name('meta.reject');
        Route::post('content-edits', [AiContentController::class, 'suggest'])
            ->middleware('throttle:admin-ai')->name('content.suggest');
        Route::post('content-edits/{suggestion}/apply', [AiContentController::class, 'apply'])
            ->whereNumber('suggestion')->middleware('can:ai.apply')->name('content.apply');
        Route::post('content-edits/{suggestion}/reject', [AiContentController::class, 'reject'])
            ->whereNumber('suggestion')->name('content.reject');

        // FAQ, alt text, SEO fixes, product descriptions (AiToolsController).
        Route::controller(AiToolsController::class)->group(function () {
            Route::post('faq', 'faqSuggest')->middleware('throttle:admin-ai')->name('faq.suggest');
            Route::post('faq/{suggestion}/apply', 'faqApply')->whereNumber('suggestion')->middleware('can:ai.apply')->name('faq.apply');
            Route::post('alt/missing', 'altMissing')->name('alt.missing');
            Route::post('alt', 'altSuggest')->middleware('throttle:admin-ai')->name('alt.suggest');
            Route::post('alt/{suggestion}/apply', 'altApply')->whereNumber('suggestion')->middleware('can:ai.apply')->name('alt.apply');
            Route::post('seo-fixes', 'seoSuggest')->middleware('throttle:admin-ai')->name('seo.suggest');
            Route::post('seo-fixes/{suggestion}/apply', 'seoApply')->whereNumber('suggestion')->middleware('can:ai.apply')->name('seo.apply');
            Route::post('product-descriptions', 'productSuggest')->middleware('throttle:admin-ai')->name('product.suggest');
            Route::post('product-descriptions/{suggestion}/apply', 'productApply')->whereNumber('suggestion')->middleware('can:ai.apply')->name('product.apply');
            Route::post('collection-intros', 'collectionSuggest')->middleware('throttle:admin-ai')->name('collection.suggest');
            Route::post('collection-intros/{suggestion}/apply', 'collectionApply')->whereNumber('suggestion')->middleware('can:ai.apply')->name('collection.apply');
            Route::post('internal-links', 'linksSuggest')->middleware('throttle:admin-ai')->name('links.suggest');
            Route::post('internal-links/{suggestion}/apply', 'linksApply')->whereNumber('suggestion')->middleware('can:ai.apply')->name('links.apply');
            Route::post('suggestions/{suggestion}/reject', 'reject')->whereNumber('suggestion')->name('reject');
        });

        // Bulk SEO: one gateway job, results reviewed here (AiBulkController).
        Route::get('bulk', [AiBulkController::class, 'index'])->name('bulk.index');
        Route::post('bulk/estimate', [AiBulkController::class, 'estimate'])->middleware('throttle:admin-ai')->name('bulk.estimate');
        Route::post('bulk', [AiBulkController::class, 'store'])->middleware('throttle:admin-ai')->name('bulk.store');
        Route::get('bulk/{job}', [AiBulkController::class, 'show'])->whereNumber('job')->middleware('throttle:admin-gateway-read')->name('bulk.show');
        Route::post('bulk/{job}/apply', [AiBulkController::class, 'apply'])->whereNumber('job')->middleware('can:ai.apply')->name('bulk.apply');

        // Keywords & competitors (Hero 5+). Data from a paid provider, limited on the gateway.
        Route::middleware('ai.access:ai_research')->prefix('research')->name('research.')
            ->controller(AiResearchController::class)->group(function () {
                Route::get('/', 'index')->middleware('throttle:admin-gateway-read')->name('index');
                Route::get('status', 'status')->middleware('throttle:admin-gateway-read')->name('status');
                Route::post('competitors', 'storeCompetitor')->middleware('throttle:admin-ai')->name('competitors.store');
                Route::delete('competitors/{domain}', 'destroyCompetitor')->where('domain', '[A-Za-z0-9.-]+')->name('competitors.destroy');
                Route::post('competitors/suggest', 'suggest')->middleware('throttle:admin-ai')->name('competitors.suggest');
                Route::post('refresh', 'refresh')->middleware('throttle:admin-ai')->name('refresh');
                Route::post('keywords', 'storeKeywords')->middleware('throttle:admin-ai')->name('keywords.store');
                Route::delete('keywords/{keyword}', 'destroyKeyword')->whereNumber('keyword')->name('keywords.destroy');
                Route::post('topics', 'topics')->middleware('throttle:admin-ai')->name('topics');
            });

        // "Sửa với AI" in the inline storefront editor: rewrite a selected piece of text.
        Route::post('rewrite', AiRewriteController::class)->middleware('throttle:admin-ai')->name('rewrite');

        // This week's SEO work (Search Console + the site index, ranked by the gateway).
        Route::get('seo-tasks', [AiSeoTasksController::class, 'index'])->middleware('throttle:admin-gateway-read')->name('seo-tasks.index');
        Route::get('seo-tasks/summary', [AiSeoTasksController::class, 'summary'])->middleware('throttle:admin-gateway-read')->name('seo-tasks.summary');
        Route::post('seo-tasks/{task}/advice', [AiSeoTasksController::class, 'advice'])
            ->where('task', '[a-f0-9]{16}')->middleware('throttle:admin-ai')->name('seo-tasks.advice');
        Route::post('seo-tasks/{task}/status', [AiSeoTasksController::class, 'mark'])
            ->where('task', '[a-f0-9]{16}')->name('seo-tasks.status');
        Route::post('seo-tasks/{task}/fix/{step}', [AiSeoTasksController::class, 'fixStep'])
            ->where(['task' => '[a-f0-9]{16}', 'step' => '[a-z]+'])->middleware('throttle:admin-ai')->name('seo-tasks.fix.step');
        Route::post('seo-tasks/{task}/fix', [AiSeoTasksController::class, 'fixApply'])
            ->where('task', '[a-f0-9]{16}')->middleware('can:ai.apply')->name('seo-tasks.fix.apply');

        Route::middleware(['feature:cms_page', 'can:posts.create'])->group(function () {
            Route::get('post-drafts/create', [AiPostDraftController::class, 'create'])->name('post-drafts.create');
            // A draft is a new post: it counts against the package's data units like any other.
            Route::post('post-drafts', [AiPostDraftController::class, 'store'])
                ->middleware(['package.limit:data_unit', 'throttle:admin-ai'])->name('post-drafts.store');
        });
    });

    Route::middleware('can:media.view')->group(function () {
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/resources', [MediaController::class, 'resources'])->name('media.resources');
    });
    Route::post('media/upload', [MediaController::class, 'upload'])->middleware(['can:media.create', 'throttle:admin-heavy'])->name('media.upload');
    Route::delete('media/delete', [MediaController::class, 'destroy'])->middleware('can:media.delete')->name('media.delete');
    Route::delete('media/bulk-delete', [MediaController::class, 'bulkDestroy'])->middleware('can:media.delete')->name('media.bulk-delete');
    Route::post('media/cloudinary/connect', [MediaController::class, 'connectCloudinary'])
        ->middleware(['can:media.view', 'can:settings.update', 'throttle:admin-integration'])
        ->name('media.cloudinary.connect');
    Route::delete('media/cloudinary/disconnect', [MediaController::class, 'disconnectCloudinary'])
        ->middleware(['can:media.view', 'can:settings.update', 'throttle:admin-integration'])
        ->name('media.cloudinary.disconnect');

    // Installing an offered release: the website's admin too, unless a superadmin turned that
    // off for this website (CoreUpdateAccess). Only queued here; cron runs it.
    Route::middleware('core-update.access')->group(function () {
        Route::get('core-update', [CoreUpdateController::class, 'index'])->name('core-update.index');
        Route::get('core-update/status', [CoreUpdateController::class, 'status'])->name('core-update.status');
        Route::post('core-update/check', [CoreUpdateController::class, 'check'])->middleware('throttle:admin-integration')->name('core-update.check');
        Route::post('core-update', [CoreUpdateController::class, 'update'])->name('core-update.update');
    });

    // Feature flags and impersonation stay superadmin-only: they can hand out
    // access rather than merely use it.
    Route::middleware('superadmin')->group(function () {
        Route::get('features', [FeatureController::class, 'index'])->name('features.index');
        Route::post('features', [FeatureController::class, 'update'])->name('features.update');
        Route::post('features/toggle', [FeatureController::class, 'toggle'])->name('features.toggle');
        Route::post('features/group-toggle', [FeatureController::class, 'toggleGroup'])->name('features.group-toggle');
        Route::post('features/sync-package', [FeatureController::class, 'syncPackage'])->name('features.sync-package');
        Route::post('features/reset-overrides', [FeatureController::class, 'resetOverrides'])->name('features.reset-overrides');

        // Core update repairs and who may update: superadmin only. Updates are only queued here.
        Route::post('core-update/rollback', [CoreUpdateController::class, 'rollback'])->name('core-update.rollback');
        Route::post('core-update/baseline', [CoreUpdateController::class, 'baseline'])->name('core-update.baseline');
        Route::put('core-update/access', [CoreUpdateController::class, 'access'])->name('core-update.access');

        // The website's extensions/ and what became of each (read-only: extension:enable|disable).
        Route::get('extensions', [ExtensionController::class, 'index'])->name('extensions.index');
    });

    Route::get('languages', [LanguageController::class, 'index'])->middleware(['feature:multilingual', 'can:languages.view'])->name('languages.index');
    Route::post('languages', [LanguageController::class, 'store'])->middleware(['feature:multilingual', 'can:languages.create'])->name('languages.store');
    Route::put('languages/preferences', [LanguageController::class, 'updatePreferences'])->middleware(['feature:multilingual', 'can:languages.update'])->name('languages.preferences');
    Route::put('languages/{language}', [LanguageController::class, 'update'])->middleware(['feature:multilingual', 'can:languages.update'])->name('languages.update');

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
        Route::post('posts/import-wordpress', [PostController::class, 'importWordPress'])->middleware(['can:posts.create', 'throttle:admin-heavy'])->name('posts.import-wordpress');
        Route::patch('posts/bulk', [PostController::class, 'bulk'])->middleware('can:posts.update')->name('posts.bulk');
        Route::post('post-categories/sort', [PostCategoryController::class, 'sort'])->middleware('can:posts.update')->name('post-categories.sort');
        Route::put('post-categories/{post_category}/quick-update', [PostCategoryController::class, 'quickUpdate'])->middleware('can:posts.update')->name('post-categories.quick-update');

        Route::resource('post-categories', PostCategoryController::class)->only(['create', 'store'])->middleware('can:posts.create');
        Route::resource('post-categories', PostCategoryController::class)->only(['index'])->middleware('can:posts.view');
        Route::resource('post-categories', PostCategoryController::class)->only(['edit', 'update'])->middleware('can:posts.update');
        Route::resource('post-categories', PostCategoryController::class)->only(['destroy'])->middleware('can:posts.delete');

        Route::resource('posts', PostController::class)->only(['create', 'store'])->middleware(['can:posts.create', 'package.limit:data_unit']);
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
        Route::post('site-lists/items/duplicate', [SiteBlockController::class, 'duplicateListItem'])->name('site-lists.items.duplicate');
        Route::delete('site-lists/items', [SiteBlockController::class, 'removeListItem'])->name('site-lists.items.destroy');
        Route::patch('site-lists/items/order', [SiteBlockController::class, 'reorderListItems'])->name('site-lists.items.reorder');
        // How a section renders: which of the shapes it already knows, and whether
        // it shows at all. Same gate again — choosing a section's variant is the
        // same power over the same site as rewriting its heading.
        Route::patch('site-sections', [SiteSectionController::class, 'update'])->name('site-sections.update');
        Route::delete('site-sections', [SiteSectionController::class, 'restore'])->name('site-sections.restore');

        /*
         * History. Read behind the same gate as the write: it names the admins who
         * edited the site, which no customer or guest is owed. The revision is a
         * route binding and the target is derived from it — never from the request —
         * so a crafted call cannot write one region's snapshot onto another.
         */
        Route::get('site-blocks/revisions', [SiteBlockController::class, 'revisions'])->name('site-blocks.revisions.index');
        Route::post('site-blocks/revisions/{revision}/restore', [SiteBlockController::class, 'restoreRevision'])->name('site-blocks.revisions.restore');
        Route::get('site-sections/revisions', [SiteSectionController::class, 'revisions'])->name('site-sections.revisions.index');
        Route::post('site-sections/revisions/{revision}/restore', [SiteSectionController::class, 'restoreRevision'])->name('site-sections.revisions.restore');
    });

    Route::middleware('feature:cms_page')->group(function () {
        Route::get('pages/{page}/preview', [PageController::class, 'preview'])->middleware('can:pages.view')->name('pages.preview');
        Route::patch('pages/{page}/inline', [PageController::class, 'inlineUpdate'])->middleware(['can:pages.update', 'throttle:admin-page-inline'])->name('pages.inline-update');
        // Read path for the storefront history panel. Same gate and throttle as
        // the inline write it sits next to — the list names the admins who edited
        // the site, which is not something a visitor is entitled to read.
        Route::get('pages/{page}/revisions', [PageController::class, 'revisions'])->middleware(['can:pages.update', 'throttle:admin-page-inline'])->name('pages.revisions.index');
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

    // Promotions / flash sales belong to the voucher feature ("Khuyến mãi & mã giảm giá", Hero 2+)
    // and discount products, so the catalogue must be on too.
    Route::middleware(['feature:catalog', 'feature:voucher'])->group(function () {
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

    // Old URLs and where they now go; the storefront URLs that answered 404 (RedirectService).
    Route::get('redirects', [RedirectController::class, 'index'])->middleware('can:redirects.view')->name('redirects.index');
    Route::get('redirects/export', [RedirectController::class, 'export'])->middleware('can:redirects.view')->name('redirects.export');
    Route::post('redirects', [RedirectController::class, 'store'])->middleware('can:redirects.create')->name('redirects.store');
    Route::post('redirects/import', [RedirectController::class, 'import'])->middleware('can:redirects.create')->name('redirects.import');
    Route::patch('redirects/bulk', [RedirectController::class, 'bulk'])->middleware('can:redirects.update')->name('redirects.bulk');
    Route::put('redirects/{redirect}', [RedirectController::class, 'update'])->whereNumber('redirect')->middleware('can:redirects.update')->name('redirects.update');
    Route::delete('redirects/{redirect}', [RedirectController::class, 'destroy'])->whereNumber('redirect')->middleware('can:redirects.delete')->name('redirects.destroy');
    Route::delete('redirects/not-found', [RedirectController::class, 'clearNotFound'])->middleware('can:redirects.delete')->name('redirects.not-found.clear');
    Route::delete('redirects/not-found/{notFoundLog}', [RedirectController::class, 'destroyNotFound'])->whereNumber('notFoundLog')->middleware('can:redirects.delete')->name('redirects.not-found.destroy');

    Route::get('contact-submissions', [ContactSubmissionController::class, 'index'])->middleware('can:contacts.view')->name('contact-submissions.index');
    Route::patch('contact-submissions/bulk', [ContactSubmissionController::class, 'bulk'])->middleware('can:contacts.delete')->name('contact-submissions.bulk');
    Route::patch('contact-submissions/{contactSubmission}/toggle-read', [ContactSubmissionController::class, 'toggleRead'])->middleware('can:contacts.update')->name('contact-submissions.toggle-read');
    Route::patch('contact-submissions/{contactSubmission}/not-spam', [ContactSubmissionController::class, 'notSpam'])->middleware('can:contacts.update')->name('contact-submissions.not-spam');
    Route::delete('contact-submissions/{contactSubmission}', [ContactSubmissionController::class, 'destroy'])->middleware('can:contacts.delete')->name('contact-submissions.destroy');

    Route::get('settings', [SettingController::class, 'index'])->middleware('can:settings.view')->name('settings.index');
    Route::post('settings', [SettingController::class, 'update'])->middleware('can:settings.update')->name('settings.update');
    Route::post('settings/analytics/check', [AnalyticsController::class, 'check'])
        ->middleware(['can:settings.update', 'throttle:6,1'])->name('settings.analytics.check');
    Route::post('settings/search-console/check', [AnalyticsController::class, 'checkSearchConsole'])
        ->middleware(['can:settings.update', 'throttle:6,1'])->name('settings.search-console.check');

    Route::get('notification-settings', [NotificationSettingController::class, 'index'])->middleware('can:settings.view')->name('notification-settings.index');
    Route::post('notification-settings', [NotificationSettingController::class, 'update'])->middleware('can:settings.update')->name('notification-settings.update');
    Route::post('notification-settings/test-email', [NotificationSettingController::class, 'sendTestEmail'])->middleware(['can:settings.update', 'throttle:5,1'])->name('notification-settings.test-email');
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
        Route::post('banners/sort', [BannerController::class, 'sort'])->middleware('can:banners.update')->name('banners.sort');
        Route::resource('banners', BannerController::class)->only(['create', 'store'])->middleware(['can:banners.create']);
        Route::resource('banners', BannerController::class)->only(['index'])->middleware('can:banners.view');
        Route::resource('banners', BannerController::class)->only(['edit', 'update'])->middleware('can:banners.update');
        Route::resource('banners', BannerController::class)->only(['destroy'])->middleware('can:banners.delete');
    });

    // Partner / customer logos and their groups (Đối tác). No feature switch: like redirects, every
    // package has it; a theme that shows no logo strip simply never asks for a group.
    Route::patch('partners/bulk', [PartnerController::class, 'bulk'])->middleware('can:partners.update')->name('partners.bulk');
    Route::post('partners/sort', [PartnerController::class, 'sort'])->middleware('can:partners.update')->name('partners.sort');
    Route::resource('partners', PartnerController::class)->only(['create', 'store'])->middleware('can:partners.create');
    Route::resource('partners', PartnerController::class)->only(['index'])->middleware('can:partners.view');
    Route::resource('partners', PartnerController::class)->only(['edit', 'update'])->middleware('can:partners.update');
    Route::resource('partners', PartnerController::class)->only(['destroy'])->middleware('can:partners.delete');
    Route::resource('partner-groups', PartnerGroupController::class)->only(['create', 'store'])->middleware('can:partners.create');
    Route::resource('partner-groups', PartnerGroupController::class)->only(['index'])->middleware('can:partners.view');
    Route::resource('partner-groups', PartnerGroupController::class)->only(['edit', 'update'])->middleware('can:partners.update');
    Route::resource('partner-groups', PartnerGroupController::class)->only(['destroy'])->middleware('can:partners.delete');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('can:activity_logs.view')->name('activity-logs.index');
    // Wiping the audit trail is superadmin-only, like feature flags.
    Route::middleware('superadmin')->group(function () {
        Route::delete('activity-logs/bulk', [ActivityLogController::class, 'bulkDestroy'])->name('activity-logs.bulk-destroy');
        Route::delete('activity-logs', [ActivityLogController::class, 'destroyAll'])->name('activity-logs.destroy-all');
    });

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
        Route::patch('products/{product}/quick-update', [ProductController::class, 'quickUpdate'])->middleware('can:products.update')->name('products.quick-update');
        Route::get('products/export', [ProductController::class, 'export'])->middleware('can:products.view')->name('products.export');
        Route::get('products/template/{type}', [ProductController::class, 'downloadTemplate'])->middleware('can:products.view')->name('products.template');
        Route::post('products/import', [ProductController::class, 'import'])->middleware(['can:products.create', 'throttle:admin-heavy'])->name('products.import');
        Route::resource('products', ProductController::class)->only(['create', 'store'])->middleware(['can:products.create', 'package.limit:data_unit']);
        Route::resource('products', ProductController::class)->only(['index', 'show'])->middleware('can:products.view');
        Route::resource('products', ProductController::class)->only(['edit', 'update'])->middleware('can:products.update');
        Route::resource('products', ProductController::class)->only(['destroy'])->middleware('can:products.delete');

        // Variants are a package feature of their own (Hero 3+).
        Route::middleware('feature:product_variant')->group(function () {
            Route::get('products/{product}/options', [ProductOptionController::class, 'edit'])->middleware('can:products.update')->name('products.options.edit');
            Route::put('products/{product}/options', [ProductOptionController::class, 'update'])->middleware('can:products.update')->name('products.options.update');
            Route::post('products/{product}/variants/generate', [ProductVariantController::class, 'generate'])->middleware('can:products.create')->name('products.variants.generate');
            Route::resource('products.variants', ProductVariantController::class)->only(['create', 'store'])->middleware('can:products.create');
            Route::resource('products.variants', ProductVariantController::class)->only(['edit', 'update'])->middleware('can:products.update');
            Route::resource('products.variants', ProductVariantController::class)->only(['destroy'])->middleware('can:products.delete');
        });
    });
});
