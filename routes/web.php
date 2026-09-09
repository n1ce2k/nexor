<?php

use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\RobotsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('robots.txt', RobotsController::class)->name('robots');

Route::view('search', 'site.search')->name('search');

/*
 * Catch-alls for infoblock pages, kept last so they never shadow a real route.
 * The `admin` prefix is excluded because the panel registers its own routes.
 */
Route::get('/{code}', [PageController::class, 'show'])
    ->where('code', '^(?!admin)[a-z0-9-]+$')
    ->name('page');

/*
 * Everything below an infoblock is read like a folder path: each segment is a
 * section until one of them is not, and that last one is an element. So
 * /katalog/mebel is a section, /katalog/mebel/stulya a nested section, and
 * /katalog/mebel/stul the element inside it.
 */
Route::get('/{code}/{path}', [PageController::class, 'inside'])
    ->where('code', '^(?!admin)[a-z0-9-]+$')
    ->where('path', '^[a-zA-Z0-9_/-]+$')
    ->name('page.inside');
