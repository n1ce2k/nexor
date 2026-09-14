<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Routing\Controller;
use Nexor\Cms\Support\PanelAssets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Отдаёт готовую сборку админки из пакета: vendor/n1ce2k/<пакет>/dist.
 *
 * Без сессий и авторизации — это статика, как livewire.js. Файлы с хешем в
 * имени и адреса с версией кешируются браузером на год.
 */
class AssetController extends Controller
{
    /** @var array<string, string> */
    protected const TYPES = [
        'js' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];

    public function __invoke(string $package, string $path): BinaryFileResponse
    {
        $dist = PanelAssets::distributions()[$package] ?? null;

        abort_if($dist === null, 404);

        $base = realpath($dist);
        $file = realpath($dist.'/'.$path);
        $extension = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));

        // Только файлы внутри сборки и только известных типов: ../ и manifest.json мимо.
        abort_unless(
            $base && $file && is_file($file)
            && str_starts_with($file, $base.DIRECTORY_SEPARATOR)
            && isset(self::TYPES[$extension]),
            404,
        );

        return response()->file($file, [
            'Content-Type' => self::TYPES[$extension],
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
