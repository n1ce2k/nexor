{{--
    Обёртка блоков конструктора.

    Приходит: $items — [{ id, type, view, data }], $element — элемент инфоблока,
    $showSidebar — выводить оглавление, $sidebarItems — [{ text, targetId }],
    $standalone — подключены стили и скрипт модуля (data-pb-standalone).

    Каждый блок получает свои данные плюс $blockId и $element.

    Свой шаблон: php artisan nexor-pagebuilder:component content
--}}

<div class="pb-wrapper{{ $showSidebar ? ' has-sidebar' : '' }}" data-pb-standalone="{{ $standalone ? 'true' : 'false' }}">
    <div class="pb-content">
        @foreach ($items as $item)
            <div @if ($showSidebar) id="block-{{ $item['id'] }}" @endif class="pb-block-wrapper" data-type="{{ $item['type'] }}">
                @include($item['view'], $item['data'] + ['blockId' => $item['id'], 'element' => $element])
            </div>
        @endforeach
    </div>

    @if ($showSidebar)
        <aside class="pb-aside">
            <div class="pb-aside__menu js-pb-pin-menu">
                <nav class="pb-aside__menu-list" role="navigation" aria-label="Содержание страницы">
                    @foreach ($sidebarItems as $sidebarItem)
                        <div class="pb-aside__menu-item">
                            <a href="#block-{{ $sidebarItem['targetId'] }}" class="pb-aside__menu-link js-scroll-to" rel="nofollow">{{ $sidebarItem['text'] }}</a>
                        </div>
                    @endforeach
                </nav>
            </div>
        </aside>
    @endif
</div>
