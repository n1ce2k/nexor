<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nexor\Cms\Support\Cookies;

/**
 * Приём согласия с публичной страницы.
 *
 * Ответ отдаёт разрешённые коды: счётчики включаются сразу, без перезагрузки,
 * иначе посетитель, нажавший «принять», для аналитики остаётся невидимым до
 * следующей страницы.
 */
class CookieConsentController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(Cookies::enabled(), 404);

        $data = $request->validate([
            'analytics' => ['boolean'],
            'marketing' => ['boolean'],
        ]);

        $preferences = [
            'technical' => true,
            'analytics' => (bool) ($data['analytics'] ?? false),
            'marketing' => (bool) ($data['marketing'] ?? false),
        ];

        Cookies::record($preferences, $request);

        $codes = [];

        foreach (['head', 'body'] as $placement) {
            $codes[$placement] = Cookies::codesFor(
                collect($preferences)->filter()->keys()->all(),
                $placement,
            );
        }

        return response()
            ->json(['preferences' => $preferences, 'codes' => $codes])
            // Cookie ставит сервер: срок и путь заданы настройкой, а не скриптом.
            ->cookie(
                (string) Cookies::get('cookie_name'),
                (string) json_encode($preferences),
                (int) Cookies::get('lifetime') * 24 * 60,
                '/',
                null,
                $request->secure(),
                false,
            );
    }
}
