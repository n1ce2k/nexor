<?php

namespace Nexor\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nexor\Cms\Models\CookieCounter;
use Nexor\Cms\Support\Cookies;
use Symfony\Component\HttpFoundation\Response;

/**
 * Подключает счётчики, разрешённые посетителем.
 *
 * Код вставляется в ответ, а не шаблоном сайта: иначе каждый сайт обязан был бы
 * помнить про строку в макете, а забытая строка здесь означает не «нет
 * счётчика», а «счётчик работает без согласия».
 *
 * В обычном режиме решение принимает сервер и неразрешённый код в страницу не
 * попадает вовсе. В режиме JS (страница отдаётся кешем целиком) в неё уходит
 * список кодов, а выбирает уже скрипт по cookie.
 */
class InjectCookieCounters
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->suits($request, $response)) {
            return $response;
        }

        return Cookies::get('js_mode')
            ? $this->injectScript($response)
            : $this->injectCodes($request, $response);
    }

    protected function suits(Request $request, Response $response): bool
    {
        return Cookies::enabled()
            && ! $request->ajax()
            && $response->isSuccessful()
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $request->is(trim((string) config('nexor.route.prefix', 'admin'), '/').'*');
    }

    /**
     * Серверный режим: в страницу попадает только разрешённое.
     */
    protected function injectCodes(Request $request, Response $response): Response
    {
        $head = implode("\n", Cookies::codes('head', $request));
        $body = implode("\n", Cookies::codes('body', $request));

        $content = (string) $response->getContent();
        $content = $this->insert($content, '</head>', $head);
        $content = $this->insert($content, '</body>', $body);

        $response->setContent($content);

        return $response;
    }

    /**
     * Режим JS: коды уезжают в браузер, выбирает их скрипт.
     */
    protected function injectScript(Response $response): Response
    {
        $categories = array_keys(CookieCounter::CATEGORIES);

        $payload = [
            'cookie' => Cookies::get('cookie_name'),
            'head' => [],
            'body' => [],
        ];

        foreach ($categories as $category) {
            foreach (['head', 'body'] as $placement) {
                $codes = Cookies::codesFor([$category], $placement);

                if ($codes !== []) {
                    $payload[$placement][$category] = $codes;
                }
            }
        }

        if ($payload['head'] === [] && $payload['body'] === []) {
            return $response;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        // Скрипт подключается здесь, а не баннером: баннер показывают только
        // тому, кто ещё не ответил, а коды нужны как раз ответившему.
        $markup = '<script id="nexor-cookies-counters" type="application/json">'.$json.'</script>'
            .'<script src="'.e(route('nexor.cookies.asset', ['file' => 'cookies.js', 'v' => $this->stamp()])).'"></script>';

        $response->setContent($this->insert((string) $response->getContent(), '</body>', $markup));

        return $response;
    }

    /**
     * Отпечаток скрипта: правка доезжает до браузера, не дожидаясь кеша.
     */
    protected function stamp(): string
    {
        $path = dirname(__DIR__, 3).'/resources/assets/cookies.js';

        return is_file($path) ? substr(md5_file($path), 0, 10) : '1';
    }

    protected function insert(string $content, string $tag, string $markup): string
    {
        $at = strripos($content, $tag);

        return $markup === '' || $at === false
            ? $content
            : substr($content, 0, $at).$markup."\n".substr($content, $at);
    }
}
