<?php

use App\Http\Controllers\Client\CategoryController;
use App\Http\Controllers\Client\PageController;
use App\Http\Controllers\Client\PostCategoryController;
use Illuminate\Support\Facades\Route;

Route::get('pages/{slug}', [PageController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('pages.show');

Route::get('danh-muc/{slug}', [CategoryController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('categories.show');

Route::get('chuyen-muc/{slug}', [PostCategoryController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('post-categories.show');

/*
 * Sandbox for the inline editing toolbar.
 *
 * Local only, and deliberately not a Page record: a core reused across projects
 * must not ship a demo page that a customer can stumble onto — the same reason
 * the seeded sample invoices were removed. Delete this block once the toolbar
 * has been signed off, or keep it as the place to reproduce editor bugs.
 */
if (app()->environment(['local', 'testing'])) {
    Route::view('sandbox/inline-editor', 'client.dev.toolbar-sandbox')
        ->name('dev.toolbar-sandbox');
    Route::view('sandbox/inline-editor-stress', 'client.dev.toolbar-stress')
        ->name('dev.toolbar-stress');
}
