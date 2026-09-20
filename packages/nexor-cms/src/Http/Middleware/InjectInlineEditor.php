<?php

namespace Nexor\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nexor\Cms\Support\InlineEditor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Включает режим правки и подмешивает в страницу редактор.
 *
 * Скрипт вставляется ответом, а не `@stack` в макете: макет у каждого сайта
 * свой, и требовать от него специальной строки ради режима правки — значит
 * сломать правку на всех сайтах, где макет писали раньше.
 */
class InjectInlineEditor
{
    public function handle(Request $request, Closure $next): Response
    {
        // ?nexor-edit=1 включает режим, 0 выключает — ссылка из панели.
        if ($request->has('nexor-edit') && InlineEditor::allowed()) {
            $request->session()->put(InlineEditor::KEY, $request->boolean('nexor-edit'));
        }

        $response = $next($request);

        return $this->shouldInject($request, $response)
            ? $this->inject($response)
            : $response;
    }

    protected function shouldInject(Request $request, Response $response): bool
    {
        return InlineEditor::active()
            && ! $request->ajax()
            && $response->isSuccessful()
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $request->is(trim(config('nexor.route.prefix', 'admin'), '/').'*');
    }

    protected function inject(Response $response): Response
    {
        $content = (string) $response->getContent();
        $at = strripos($content, '</body>');

        if ($at === false) {
            return $response;
        }

        $response->setContent(substr($content, 0, $at).$this->markup().substr($content, $at));

        return $response;
    }

    protected function markup(): string
    {
        return view('nexor::inline.editor', [
            'base' => url('nexor/content'),
            'assets' => [
                'css' => route('nexor.content.asset', ['file' => 'inline-editor.css']),
                'js' => route('nexor.content.asset', ['file' => 'inline-editor.js']),
            ],
        ])->render();
    }
}
