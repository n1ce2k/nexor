<?php

namespace Nexor\PageBuilder\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Стили и скрипт блоков для сайта — прямо из пакета, без публикации в public/.
 */
class AssetController extends Controller
{
    /** @var array<string, string> */
    protected const FILES = [
        'pagebuilder.css' => 'text/css; charset=UTF-8',
        'pagebuilder.js' => 'application/javascript; charset=UTF-8',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(isset(self::FILES[$file]), 404);

        return response()->file(dirname(__DIR__, 3).'/resources/assets/'.$file, [
            'Content-Type' => self::FILES[$file],
            // Адрес версионирован ?v=, поэтому кешировать можно надолго.
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
