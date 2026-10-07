<?php

use Extensions\Example\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

// Served at /{locale}/admin/ext/example/..., behind the admin login. Names become ext.example.*
Route::get('notes', [NoteController::class, 'index'])->middleware('can:example.view')->name('notes.index');
Route::post('notes', [NoteController::class, 'store'])->middleware('can:example.manage')->name('notes.store');
