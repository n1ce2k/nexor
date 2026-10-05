<?php

namespace Nexor\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Nexor\Cms\Support\RootHtaccess;
use Symfony\Component\HttpFoundation\Response;

/**
 * Не даёт папке `public` попасть в адреса сайта.
 *
 * На хостинге, где запросы уводит в `public` корневой .htaccess, браузер иногда
 * оказывается на `/public/...` — после редиректа, который сервер сделал уже
 * изнутри этой папки. Laravel тогда считает, что сайт стоит в подпапке, и
 * дописывает `/public` во все ссылки.
 *
 * Здесь такой запрос возвращается на чистый адрес, а ссылки строятся без
 * `/public`. Срабатывает, только когда корневой .htaccess действительно уводит
 * запросы в эту папку: сайт, намеренно открытый как `/public`, не трогаем.
 */
class HidePublicFolder
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $root = $this->cleanRoot($request);

        if ($root === null) {
            return $next($request);
        }

        // Ссылки на этой странице — уже без /public, даже если запрос не GET.
        URL::forceRootUrl($root);

        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        $query = $request->getQueryString();

        return redirect()->to($root.$request->getPathInfo().($query ? '?'.$query : ''), 301);
    }

    /**
     * Адрес сайта без утёкшей папки; null — утечки нет.
     */
    protected function cleanRoot(Request $request): ?string
    {
        $base = $request->getBaseUrl();
        $suffix = '/'.RootHtaccess::folder();

        // Обычный запрос: в адресе нет папки, файл читать незачем.
        if ($base === '' || ! str_ends_with($base, $suffix)) {
            return null;
        }

        if (! RootHtaccess::rewritesIntoPublic()) {
            return null;
        }

        return $request->getSchemeAndHttpHost().substr($base, 0, -strlen($suffix));
    }
}
