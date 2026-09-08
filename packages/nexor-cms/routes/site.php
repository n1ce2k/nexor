<?php

use Illuminate\Support\Facades\Route;
use Nexor\Cms\Http\Controllers\Site\FormController;

/**
 * Публичные маршруты пакета. Пока их один: приём форм компонента `form`.
 *
 * Ограничение по частоте стоит здесь, а не в приложении, чтобы форма была
 * защищена сразу после установки.
 */
Route::post('nexor/form', FormController::class)
    ->middleware('throttle:10,1')
    ->name('nexor.form');
