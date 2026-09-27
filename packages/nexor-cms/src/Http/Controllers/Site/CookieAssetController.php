<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Скрипт и стиль баннера из пакета.
 *
 * В отличие от редактора блоков эти файлы публичные: баннер видит как раз тот,
 * кто ещё ничего не разрешал. Адрес несёт отпечаток файла, поэтому кеш можно
 * держать долгим.
 */
class CookieAssetController extends Controller
{
    protected const FILES = [
        'cookies.js' => 'application/javascript; charset=UTF-8',
        'cookies.css' => 'text/css; charset=UTF-8',
    ];

    public function __invoke(string $file): Response
    {
        abort_unless(isset(self::FILES[$file]), 404);

        $path = dirname(__DIR__, 4).'/resources/assets/'.$file;

        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => self::FILES[$file],
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
