<?php

use Illuminate\Support\Facades\Route;

/*
 * Главная, robots.txt, /search и страницы инфоблоков (/katalog/раздел/товар)
 * приходят из пакета NEXOR CMS — см. packages/nexor-cms/routes/pages.php.
 *
 * Они зарегистрированы как fallback, поэтому свой маршрут здесь с тем же
 * адресом всегда важнее пакетного:
 *
 *     Route::view('/', 'site.landing')->name('home');
 */
