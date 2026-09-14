<?php

use Illuminate\Support\Facades\Route;
use Nexor\Shop\Http\Controllers\Site\CheckoutPageController;

/**
 * Страницы магазина на сайте. Группа уже под `nexor.feature:shop`.
 */
Route::view(config('nexor-shop.routes.cart', 'cart'), 'nexor-shop::pages.cart')->name('shop.cart');

Route::get(config('nexor-shop.routes.checkout', 'checkout'), CheckoutPageController::class)
    ->name('shop.checkout');
