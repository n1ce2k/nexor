<?php

use Illuminate\Support\Facades\Route;
use Nexor\Cms\Http\Controllers\Site\HomeController;
use Nexor\Cms\Http\Controllers\Site\PageController;
use Nexor\Cms\Http\Controllers\Site\RobotsController;
use Nexor\Cms\Support\Nexor;

/**
 * Страницы сайта: главная, robots.txt, поиск и страницы инфоблоков.
 *
 * Все маршруты помечены fallback — роутер Laravel сверяет такие последними.
 * Поэтому любой маршрут приложения (свой `/`, `/about`, `/{slug}`) всегда
 * побеждает пакетный, и порядок загрузки провайдеров ничего не ломает.
 * Выключить целиком: NEXOR_SITE_ROUTES=false.
 */
$code = '^(?!'.preg_quote(Nexor::routePrefix(), '/').'(?:/|$))[a-z0-9-]+$';

Route::get('/', [HomeController::class, 'index'])->name('home')->fallback();

Route::get('robots.txt', RobotsController::class)->name('robots')->fallback();

Route::view('search', 'site.search')->name('search')->fallback();

// /katalog — список инфоблока или страница инфоблока «Страницы».
Route::get('/{code}', [PageController::class, 'show'])
    ->where('code', $code)
    ->name('page')
    ->fallback();

/*
 * Всё внутри инфоблока читается как путь к папке: каждый сегмент — раздел,
 * пока очередной не окажется элементом; после элемента — код предложения.
 * /katalog/mebel — раздел, /katalog/mebel/stul — элемент, /katalog/mebel/stul/krasnyy — предложение.
 */
Route::get('/{code}/{path}', [PageController::class, 'inside'])
    ->where('code', $code)
    ->where('path', '^[a-zA-Z0-9_/-]+$')
    ->name('page.inside')
    ->fallback();
