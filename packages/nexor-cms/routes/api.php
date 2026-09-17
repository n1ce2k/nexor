<?php

use Illuminate\Routing\PendingResourceRegistration;
use Illuminate\Support\Facades\Route;
use Nexor\Cms\Http\Controllers\Api\ActivityLogController;
use Nexor\Cms\Http\Controllers\Api\AgreementController;
use Nexor\Cms\Http\Controllers\Api\BootstrapController;
use Nexor\Cms\Http\Controllers\Api\DashboardController;
use Nexor\Cms\Http\Controllers\Api\FeedbackFormController;
use Nexor\Cms\Http\Controllers\Api\FeedbackSubmissionController;
use Nexor\Cms\Http\Controllers\Api\IblockController;
use Nexor\Cms\Http\Controllers\Api\IblockElementController;
use Nexor\Cms\Http\Controllers\Api\IblockPropertyController;
use Nexor\Cms\Http\Controllers\Api\IblockSectionController;
use Nexor\Cms\Http\Controllers\Api\IblockTypeController;
use Nexor\Cms\Http\Controllers\Api\MailTemplateController;
use Nexor\Cms\Http\Controllers\Api\MenuController;
use Nexor\Cms\Http\Controllers\Api\MenuItemController;
use Nexor\Cms\Http\Controllers\Api\ModuleController;
use Nexor\Cms\Http\Controllers\Api\RoleController;
use Nexor\Cms\Http\Controllers\Api\SettingController;
use Nexor\Cms\Http\Controllers\Api\ToolsController;
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

Route::get('modules', [ModuleController::class, 'index'])
    ->name('modules.index')
    ->middleware('nexor.permission:modules.view');

Route::put('modules/{code}', [ModuleController::class, 'update'])
    ->name('modules.update')
    ->middleware('nexor.permission:modules.update');

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

    // Торговые предложения товара, для вкладки «Предложения».
    Route::get('elements/{element}/offers', [IblockElementController::class, 'offers'])
        ->name('elements.offers')
        ->middleware('nexor.iblock:view');

    // Привязка уже существующих предложений к товару.
    Route::post('elements/{element}/offers', [IblockElementController::class, 'attachOffers'])
        ->name('elements.offers.attach')
        ->middleware('nexor.iblock:update');

    // Tabs of the element form, editable per infoblock.
    Route::put('form-layout', [IblockElementController::class, 'saveLayout'])
        ->name('form-layout.update')
        ->middleware('nexor.permission:iblocks.update');
});

$guard(Route::apiResource('menus', MenuController::class), 'nexor.permission', 'menus.');

Route::get('menu-meta', [MenuController::class, 'meta'])
    ->name('menus.meta')
    ->middleware('nexor.permission:menus.view');

Route::middleware('nexor.permission:menus.update')->group(function (): void {
    Route::post('menus/{menu}/items', [MenuItemController::class, 'store'])->name('menus.items.store');
    Route::put('menus/{menu}/items/{item}', [MenuItemController::class, 'update'])->name('menus.items.update');
    Route::delete('menus/{menu}/items/{item}', [MenuItemController::class, 'destroy'])->name('menus.items.destroy');
    Route::put('menus/{menu}/reorder', [MenuItemController::class, 'reorder'])->name('menus.items.reorder');
});

Route::get('settings', [SettingController::class, 'index'])
    ->name('settings.index')
    ->middleware('nexor.permission:settings.view');

Route::middleware('nexor.permission:settings.update')->group(function (): void {
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    // Definitions themselves, so a site can grow its own fields.
    Route::post('settings/definitions', [SettingController::class, 'store'])->name('settings.definitions.store');
    Route::put('settings/definitions/{setting}', [SettingController::class, 'updateDefinition'])
        ->name('settings.definitions.update');
    Route::delete('settings/definitions/{setting}', [SettingController::class, 'destroy'])
        ->name('settings.definitions.destroy');
});

Route::apiResource('mail-templates', MailTemplateController::class)
    ->parameters(['mail-templates' => 'template'])
    ->middlewareFor(['index', 'show'], 'nexor.permission:mail.view')
    ->middlewareFor(['store', 'update', 'destroy'], 'nexor.permission:mail.update');

Route::post('mail-templates/{template}/send', [MailTemplateController::class, 'send'])
    ->name('mail-templates.send')
    ->middleware('nexor.permission:mail.update');

/*
 * Формы обратной связи и соглашения к ним.
 */
Route::get('form-meta', [FeedbackFormController::class, 'meta'])
    ->name('forms.meta')
    ->middleware('nexor.permission:forms.view');

// Право (создание или изменение формы) проверяет сам контроллер.
Route::post('forms/telegram-test', [FeedbackFormController::class, 'testTelegram'])
    ->name('forms.telegram-test')
    ->middleware(['nexor.permission:forms.view', 'throttle:10,1']);

$guard(Route::apiResource('forms', FeedbackFormController::class), 'nexor.permission', 'forms.');

Route::prefix('forms/{form}/submissions')->name('forms.submissions.')->scopeBindings()->group(function (): void {
    Route::middleware('nexor.permission:forms.submissions.view')->group(function (): void {
        Route::get('/', [FeedbackSubmissionController::class, 'index'])->name('index');
        Route::get('{submission}', [FeedbackSubmissionController::class, 'show'])->name('show');
        Route::get('{submission}/files/{field}', [FeedbackSubmissionController::class, 'file'])
            ->where('field', '[a-z][a-z0-9_]*')
            ->name('file');
    });

    Route::delete('{submission}', [FeedbackSubmissionController::class, 'destroy'])
        ->name('destroy')
        ->middleware('nexor.permission:forms.submissions.delete');
});

$guard(Route::apiResource('agreements', AgreementController::class), 'nexor.permission', 'agreements.');

/*
 * Developer console. Authorisation is enforced inside the controller — super
 * administrator plus a config switch — because it must never become a
 * permission that the roles screen can hand out.
 */
Route::prefix('tools')->name('tools.')->group(function (): void {
    Route::get('/', [ToolsController::class, 'state'])->name('state');
    Route::post('sql', [ToolsController::class, 'sql'])->name('sql');
    Route::post('php', [ToolsController::class, 'php'])->name('php');
});

Route::get('logs', ActivityLogController::class)
    ->name('logs.index')
    ->middleware('nexor.permission:logs.view');
