<?php

use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\RobotsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('robots.txt', RobotsController::class)->name('robots');

/*
 * Catch-alls for infoblock pages, kept last so they never shadow a real route.
 * The `admin` prefix is excluded because the panel registers its own routes.
 */
Route::get('/{code}', [PageController::class, 'show'])
    ->where('code', '^(?!admin)[a-z0-9-]+$')
    ->name('page');

Route::get('/{code}/{element}', [PageController::class, 'element'])
    ->where('code', '^(?!admin)[a-z0-9-]+$')
    ->where('element', '^[a-zA-Z0-9_-]+$')
    ->name('page.element');
