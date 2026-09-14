<?php

use Illuminate\Support\Facades\Route;
use Nexor\Shop\Http\Controllers\Api\DeliveryMethodController;
use Nexor\Shop\Http\Controllers\Api\OrderController;
use Nexor\Shop\Http\Controllers\Api\OrderFieldController;
use Nexor\Shop\Http\Controllers\Api\PaymentMethodController;
use Nexor\Shop\Http\Controllers\Api\PromocodeController;
use Nexor\Shop\Http\Controllers\Api\SettingsController;

/**
 * API магазина для панели. Префикс `/admin/api`, группа уже под `nexor.feature:shop`.
 */
Route::prefix('shop')->name('shop.')->group(function (): void {
    Route::get('settings', [SettingsController::class, 'show'])
        ->name('settings.show')->middleware('nexor.permission:shop.cart.view');
    Route::put('settings', [SettingsController::class, 'update'])
        ->name('settings.update')->middleware('nexor.permission:shop.cart.update');
    Route::post('settings/telegram-test', [SettingsController::class, 'testTelegram'])
        ->name('settings.telegram-test')->middleware(['nexor.permission:shop.cart.update', 'throttle:10,1']);

    Route::get('order-fields', [OrderFieldController::class, 'index'])
        ->name('order-fields.index')->middleware('nexor.permission:shop.cart.view');
    Route::post('order-fields', [OrderFieldController::class, 'store'])
        ->name('order-fields.store')->middleware('nexor.permission:shop.cart.update');
    Route::put('order-fields/sort', [OrderFieldController::class, 'sort'])
        ->name('order-fields.sort')->middleware('nexor.permission:shop.cart.update');
    Route::put('order-fields/{field}', [OrderFieldController::class, 'update'])
        ->name('order-fields.update')->middleware('nexor.permission:shop.cart.update');
    Route::delete('order-fields/{field}', [OrderFieldController::class, 'destroy'])
        ->name('order-fields.destroy')->middleware('nexor.permission:shop.cart.update');

    Route::get('orders', [OrderController::class, 'index'])
        ->name('orders.index')->middleware('nexor.permission:shop.orders.view');
    Route::get('orders/{order}', [OrderController::class, 'show'])
        ->name('orders.show')->middleware('nexor.permission:shop.orders.view');
    Route::put('orders/{order}', [OrderController::class, 'update'])
        ->name('orders.update')->middleware('nexor.permission:shop.orders.update');
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])
        ->name('orders.destroy')->middleware('nexor.permission:shop.orders.delete');

    Route::middleware('nexor.feature:shop.promocodes')->group(function (): void {
        Route::get('promocodes', [PromocodeController::class, 'index'])
            ->name('promocodes.index')->middleware('nexor.permission:shop.promocodes.view');
        Route::post('promocodes', [PromocodeController::class, 'store'])
            ->name('promocodes.store')->middleware('nexor.permission:shop.promocodes.create');
        Route::put('promocodes/{promocode}', [PromocodeController::class, 'update'])
            ->name('promocodes.update')->middleware('nexor.permission:shop.promocodes.update');
        Route::delete('promocodes/{promocode}', [PromocodeController::class, 'destroy'])
            ->name('promocodes.destroy')->middleware('nexor.permission:shop.promocodes.delete');
    });

    Route::middleware('nexor.feature:shop.checkout')->group(function (): void {
        // Имя параметра — как переменная в контроллере: {delivery_method} → $deliveryMethod.
        foreach (['delivery-methods' => DeliveryMethodController::class, 'payment-methods' => PaymentMethodController::class] as $uri => $controller) {
            $name = str_replace('-', '_', rtrim($uri, 's'));

            Route::get($uri, [$controller, 'index'])
                ->name($uri.'.index')->middleware('nexor.permission:shop.checkout.view');
            Route::post($uri, [$controller, 'store'])
                ->name($uri.'.store')->middleware('nexor.permission:shop.checkout.update');
            Route::put($uri.'/{'.$name.'}', [$controller, 'update'])
                ->name($uri.'.update')->middleware('nexor.permission:shop.checkout.update');
            Route::delete($uri.'/{'.$name.'}', [$controller, 'destroy'])
                ->name($uri.'.destroy')->middleware('nexor.permission:shop.checkout.update');
        }
    });
});
