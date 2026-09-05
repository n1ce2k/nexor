<?php

use Illuminate\Routing\PendingResourceRegistration;
use Illuminate\Support\Facades\Route;
use Nexor\Cms\Http\Controllers\ActivityLogController;
use Nexor\Cms\Http\Controllers\Auth\LoginController;
use Nexor\Cms\Http\Controllers\DashboardController;
use Nexor\Cms\Http\Controllers\IblockController;
use Nexor\Cms\Http\Controllers\IblockElementController;
use Nexor\Cms\Http\Controllers\IblockPropertyController;
use Nexor\Cms\Http\Controllers\IblockSectionController;
use Nexor\Cms\Http\Controllers\IblockTypeController;
use Nexor\Cms\Http\Controllers\ProfileController;
use Nexor\Cms\Http\Controllers\RoleController;
use Nexor\Cms\Http\Controllers\SettingController;
use Nexor\Cms\Http\Controllers\UserController;

/**
 * Guard a resource with one permission prefix: `<prefix>.view`, `.create`,
 * `.update` and `.delete` map onto the matching resource methods.
 */
$guard = function (PendingResourceRegistration $resource, string $middleware, string $prefix): PendingResourceRegistration {
    return $resource
        ->middlewareFor(['index', 'show'], "{$middleware}:{$prefix}view")
        ->middlewareFor(['create', 'store'], "{$middleware}:{$prefix}create")
        ->middlewareFor(['edit', 'update'], "{$middleware}:{$prefix}update")
        ->middlewareFor('destroy', "{$middleware}:{$prefix}delete");
};

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store')->middleware('throttle:10,1');
});

Route::post('logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'nexor.admin'])->group(function () use ($guard): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::controller(ProfileController::class)->prefix('profile')->name('profile.')->group(function (): void {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
        Route::put('password', 'updatePassword')->name('password');
    });

    $guard(Route::resource('users', UserController::class), 'nexor.permission', 'users.');

    $guard(Route::resource('roles', RoleController::class), 'nexor.permission', 'roles.');

    $guard(
        Route::resource('iblock-types', IblockTypeController::class)->parameters(['iblock-types' => 'iblockType']),
        'nexor.permission',
        'iblock_types.',
    );

    $guard(Route::resource('iblocks', IblockController::class), 'nexor.permission', 'iblocks.');

    Route::prefix('iblocks/{iblock}')->name('iblocks.')->group(function () use ($guard): void {
        Route::resource('properties', IblockPropertyController::class)
            ->except('show')
            ->parameters(['properties' => 'property'])
            ->middleware('nexor.permission:iblocks.update');

        Route::post('properties/reorder', [IblockPropertyController::class, 'reorder'])
            ->name('properties.reorder')
            ->middleware('nexor.permission:iblocks.update');

        // Content routes are guarded by the permissions the infoblock itself owns.
        $guard(
            Route::resource('sections', IblockSectionController::class)->parameters(['sections' => 'section']),
            'nexor.iblock',
            '',
        );

        $guard(
            Route::resource('elements', IblockElementController::class)->parameters(['elements' => 'element']),
            'nexor.iblock',
            '',
        );
    });

    Route::controller(SettingController::class)->prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/', 'index')->name('index')->middleware('nexor.permission:settings.view');
        Route::put('/', 'update')->name('update')->middleware('nexor.permission:settings.update');
    });

    Route::get('logs', [ActivityLogController::class, 'index'])
        ->name('logs.index')
        ->middleware('nexor.permission:logs.view');
});
