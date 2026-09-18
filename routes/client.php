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
