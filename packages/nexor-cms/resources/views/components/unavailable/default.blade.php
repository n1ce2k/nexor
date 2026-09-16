{{--
    Заглушка компонентов сайта: инфоблок не найден или отключён в админке.

    Приходит: $iblock (код или id из вызова), $component (например, news.detail),
    $debug — включён ли APP_DEBUG. Посетителю — короткая надпись, разработчику
    в режиме отладки — что именно не нашлось.

    Своя вёрстка: php artisan nexor:component unavailable
--}}

<div class="nexor-unavailable rounded-xl border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-500" data-nexor-unavailable>
    Инфоблок недоступен
    @if ($debug)
        <span class="block text-xs text-slate-400">
            «{{ $iblock }}» не найден или отключён — компонент {{ $component }}.
        </span>
    @endif
</div>
