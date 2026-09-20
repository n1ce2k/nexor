<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Nexor\Cms\Support\InlineEditor;

/**
 * Отдаёт стиль и скрипт редактора блоков из пакета.
 *
 * Файлы небольшие и нужны только правщику, поэтому публиковать их в public/
 * при установке незачем — отдаём маршрутом, как ассеты панели.
 */
class InlineAssetController extends Controller
{
    /** Что вообще разрешено отдавать: имя файла приходит из адреса. */
    protected const FILES = [
        'inline-editor.js' => 'application/javascript; charset=UTF-8',
        'inline-editor.css' => 'text/css; charset=UTF-8',
    ];

    public function __invoke(string $file): Response
    {
        abort_unless(InlineEditor::allowed(), 403);
        abort_unless(isset(self::FILES[$file]), 404);

        $path = dirname(__DIR__, 4).'/resources/assets/'.$file;

        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => self::FILES[$file],
            // Правщик один, кеш нужен только чтобы не тянуть файл на каждой странице.
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
