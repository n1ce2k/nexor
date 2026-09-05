<?php

use Illuminate\Routing\PendingResourceRegistration;
use Illuminate\Support\Facades\Route;
use Nexor\Cms\Http\Controllers\Api\ActivityLogController;
use Nexor\Cms\Http\Controllers\Api\BootstrapController;
use Nexor\Cms\Http\Controllers\Api\DashboardController;
use Nexor\Cms\Http\Controllers\Api\IblockController;
use Nexor\Cms\Http\Controllers\Api\IblockElementController;
use Nexor\Cms\Http\Controllers\Api\IblockPropertyController;
use Nexor\Cms\Http\Controllers\Api\IblockSectionController;
use Nexor\Cms\Http\Controllers\Api\IblockTypeController;
use Nexor\Cms\Http\Controllers\Api\RoleController;
use Nexor\Cms\Http\Controllers\Api\SettingController;
use Nexor\Cms\Http\Controllers\Api\UserController;

/**
 * JSON API behind the Vue panel. Session-authenticated like the rest of the
 * admin, so the SPA needs no tokens — only the CSRF cookie.
 */
$guard = function (PendingResourceRegistration $resource, string $middleware, string $prefix): PendingResourceRegistration {
    return $resource
        ->middlewareFor(['index', 'show'], "{$middleware}:{$prefix}view")
        ->middlewareFor('store', "{$middleware}:{$prefix}create")
        ->middlewareFor('update', "{$middleware}:{$prefix}update")
        ->middlewareFor('destroy', "{$middleware}:{$prefix}delete");
};

Route::get('bootstrap', BootstrapController::class)->name('bootstrap');
Route::get('dashboard', DashboardController::class)->name('dashboard');

$guard(Route::apiResource('users', UserController::class), 'nexor.permission', 'users.');
$guard(Route::apiResource('roles', RoleController::class), 'nexor.permission', 'roles.');

$guard(
    Route::apiResource('iblock-types', IblockTypeController::class)->parameters(['iblock-types' => 'iblockType']),
    'nexor.permission',
    'iblock_types.',
);

$guard(Route::apiResource('iblocks', IblockController::class), 'nexor.permission', 'iblocks.');

Route::prefix('iblocks/{iblock}')->name('iblocks.')->group(function () use ($guard): void {
    Route::apiResource('properties', IblockPropertyController::class)
        ->parameters(['properties' => 'property'])
        ->middleware('nexor.permission:iblocks.update');

    $guard(
        Route::apiResource('sections', IblockSectionController::class)->parameters(['sections' => 'section']),
        'nexor.iblock',
        '',
    );

    $guard(
        Route::apiResource('elements', IblockElementController::class)->parameters(['elements' => 'element']),
        'nexor.iblock',
        '',
    );

    // Field definitions the element form is built from.
    Route::get('schema', [IblockElementController::class, 'schema'])
        ->name('schema')
        ->middleware('nexor.iblock:view');
});

Route::get('settings', [SettingController::class, 'index'])
    ->name('settings.index')
    ->middleware('nexor.permission:settings.view');

Route::put('settings', [SettingController::class, 'update'])
    ->name('settings.update')
    ->middleware('nexor.permission:settings.update');

Route::get('logs', ActivityLogController::class)
    ->name('logs.index')
    ->middleware('nexor.permission:logs.view');
