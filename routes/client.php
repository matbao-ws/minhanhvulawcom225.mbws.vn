<?php

use App\Http\Controllers\Client\CategoryController;
use App\Http\Controllers\Client\PageController;
use App\Http\Controllers\Client\PostCategoryController;
use Illuminate\Support\Facades\Route;

Route::get('chinh-sach/{slug}', [PageController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('pages.show');

// Old storefront path, kept so links shared before the rename still land: one hop, straight to
// the page's URL (no /{locale} prefix for the default language).
Route::get('pages/{slug}', fn (string $locale, string $slug) => redirect()->route('client.pages.show', ['locale' => $locale, 'slug' => $slug], 301))
    ->where('slug', '[A-Za-z0-9\-_]+');

Route::get('danh-muc/{slug}', [CategoryController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('categories.show');

Route::get('chuyen-muc/{slug}', [PostCategoryController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('post-categories.show');
