<?php

use Illuminate\Support\Facades\Route;
use Nexor\PageBuilder\Http\Controllers\Api\BlockController;
use Nexor\PageBuilder\Http\Controllers\Api\UploadController;

/**
 * API конструктора. Префикс `/admin/api`, вход и доступ в панель уже проверены
 * ядром; выключенный модуль отвечает 404 целиком.
 */
Route::prefix('pagebuilder')->name('pagebuilder.')->group(function (): void {
    // Палитра блоков для редактора.
    Route::get('blocks', [BlockController::class, 'index'])->name('blocks.index');

    // Картинки блоков. Права на инфоблок проверяет контроллер.
    Route::post('uploads', [UploadController::class, 'store'])->name('uploads.store');
});
