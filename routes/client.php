<?php

use App\Http\Controllers\Client\PageController;
use Illuminate\Support\Facades\Route;

Route::get('pages/{slug}', [PageController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('pages.show');
