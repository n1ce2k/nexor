<?php

use Illuminate\Support\Facades\Route;
use Nexor\Cms\Http\Controllers\Site\AgreementController;
use Nexor\Cms\Http\Controllers\Site\CaptchaController;
use Nexor\Cms\Http\Controllers\Site\FormController;

/**
 * Публичные маршруты пакета: приём форм компонента `form` и страница соглашения.
 *
 * Ограничение по частоте стоит здесь, а не в приложении, чтобы форма была
 * защищена сразу после установки.
 */
Route::post('nexor/form', FormController::class)
    ->middleware('throttle:10,1')
    ->name('nexor.form');

// Полный текст соглашения — ссылка у галочки формы, когда всплывающее окно выключено.
Route::get('agreement/{code}', AgreementController::class)
    ->where('code', '[A-Za-z0-9_-]+')
    ->name('nexor.agreement');

// Nexor Captcha: картинка задачи и новая задача для кнопки «обновить».
Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('nexor/captcha', [CaptchaController::class, 'fresh'])->name('nexor.captcha.new');
    Route::get('nexor/captcha/{id}', [CaptchaController::class, 'image'])
        ->where('id', '[A-Za-z0-9]{40}')
        ->name('nexor.captcha.image');
});
