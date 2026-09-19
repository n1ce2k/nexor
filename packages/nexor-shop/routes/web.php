<?php

use Illuminate\Support\Facades\Route;
use Nexor\Shop\Http\Controllers\Site\CheckoutPageController;
use Nexor\Shop\Http\Controllers\Site\PaymentController;

/**
 * Страницы магазина на сайте. Группа уже под `nexor.feature:shop`.
 */
Route::view(config('nexor-shop.routes.cart', 'cart'), 'nexor-shop::pages.cart')->name('shop.cart');

Route::get(config('nexor-shop.routes.checkout', 'checkout'), CheckoutPageController::class)
    ->name('shop.checkout');

/*
 * Оплата заказа. Ссылки подписаны: по номеру заказа чужую оплату не открыть.
 */
Route::get('shop/pay/{order}', [PaymentController::class, 'pay'])
    ->middleware('signed')
    ->name('shop.payment.pay');

Route::get('shop/pay/{order}/result', [PaymentController::class, 'result'])
    ->middleware('signed')
    ->name('shop.payment.result');

// Уведомления ЮKassa: без сессии и без CSRF, зато с ограничением частоты.
Route::post('shop/payment/yookassa', [PaymentController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->name('shop.payment.webhook');
