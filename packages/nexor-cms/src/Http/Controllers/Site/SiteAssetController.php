<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Routing\Controller;
use Nexor\Cms\Support\SiteAssets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Отдаёт сборку шаблонов сайта, залитую вне `public`.
 *
 * Статика без сессии и без прав: это те же файлы, что лежали бы в
 * `public/build`. Имена от Vite содержат хеш, поэтому кеш годовой.
 */
class SiteAssetController extends Controller
{
    /** @var array<string, string> */
    protected const TYPES = [
        'js' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    public function __invoke(string $path): BinaryFileResponse
    {
        $file = SiteAssets::file($path);
        $extension = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));

        // Только известные типы: manifest.json и карты исходников не отдаём.
        abort_unless($file !== null && isset(self::TYPES[$extension]), 404);

        return response()->file($file, [
            'Content-Type' => self::TYPES[$extension],
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
