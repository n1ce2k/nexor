<?php

use Illuminate\Support\Facades\Route;
use Nexor\Cms\Http\Controllers\Site\AgreementController;
use Nexor\Cms\Http\Controllers\Site\CaptchaController;
use Nexor\Cms\Http\Controllers\Site\ContentBlockController;
use Nexor\Cms\Http\Controllers\Site\CookieAssetController;
use Nexor\Cms\Http\Controllers\Site\CookieConsentController;
use Nexor\Cms\Http\Controllers\Site\FormController;
use Nexor\Cms\Http\Controllers\Site\InlineAssetController;
use Nexor\Cms\Http\Controllers\Site\SiteAssetController;
use Nexor\Cms\Support\SiteAssets;

/**
 * Публичные маршруты пакета: приём форм компонента `form` и страница соглашения.
 *
 * Ограничение по частоте стоит здесь, а не в приложении, чтобы форма была
 * защищена сразу после установки.
 */
Route::post('nexor/form', FormController::class)
    ->middleware('throttle:10,1')
    ->name('nexor.form');

/*
 * Сборка шаблонов сайта, залитая вне `public`: на хостинге без node её кладут
 * в storage/app/build, а отдаёт её PHP.
 */
Route::get(SiteAssets::PREFIX.'/{path}', SiteAssetController::class)
    ->where('path', '.+')
    ->name('nexor.asset');

// Полный текст соглашения — ссылка у галочки формы, когда всплывающее окно выключено.
Route::get('agreement/{code}', AgreementController::class)
    ->where('code', '[A-Za-z0-9_-]+')
    ->name('nexor.agreement');

/*
 * Правка блоков на странице. Маршруты публичные по адресу, но каждый проверяет
 * право content.blocks.update: режим правки живёт на сайте, а не в панели.
 */
Route::prefix('nexor/content')->name('nexor.content.')->group(function (): void {
    Route::get('assets/{file}', InlineAssetController::class)
        ->where('file', '[a-z0-9.-]+')
        ->name('asset');

    Route::post('mode', [ContentBlockController::class, 'mode'])->name('mode');

    Route::middleware('throttle:60,1')->group(function (): void {
        Route::patch('{key}', [ContentBlockController::class, 'update'])->name('update');
        Route::post('{key}/image', [ContentBlockController::class, 'image'])->name('image');
        Route::delete('{key}', [ContentBlockController::class, 'destroy'])->name('reset');
    });
});

/*
 * Согласие на cookie. Маршруты публичные: баннер видит как раз тот, кто ещё
 * ничего не разрешал, а значит и не вошёл.
 */
Route::get('nexor/cookies/assets/{file}', CookieAssetController::class)
    ->where('file', '[a-z0-9.-]+')
    ->name('nexor.cookies.asset');

Route::post('nexor/cookies', CookieConsentController::class)
    ->middleware('throttle:30,1')
    ->name('nexor.cookies.consent');

// Nexor Captcha: картинка задачи и новая задача для кнопки «обновить».
Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('nexor/captcha', [CaptchaController::class, 'fresh'])->name('nexor.captcha.new');
    Route::get('nexor/captcha/{id}', [CaptchaController::class, 'image'])
        ->where('id', '[A-Za-z0-9]{40}')
        ->name('nexor.captcha.image');
});
