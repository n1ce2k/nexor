{{--
    Блок «Табы».

    Приходит: $items — [{ id, title, content }] (content — HTML, очищен при
    сохранении), $activeTabId, $cssClass, $blockId, $element.

    Переключение — скрипт модуля по data-tabs (или свой скрипт сайта).
--}}

@php
    $activeTabId = $activeTabId ?? ($items[0]['id'] ?? null);
    $uniqueBlockId = 'tab_'.$blockId;
@endphp

@if (! empty($items))
    <section class="pb-block pb-tabs {{ $cssClass }}">
        <div class="pb-container">
            <div class="nw-pb-tabs" data-tabs>

                <div class="nw-pb-tabs__nav" role="tablist">
                    @foreach ($items as $item)
                        <button type="button"
                                class="nw-pb-tabs__btn"
                                data-tab-target="{{ $uniqueBlockId }}_{{ $item['id'] }}"
                            @if ($item['id'] === $activeTabId) data-active @endif>
                            {{ $item['title'] }}
                        </button>
                    @endforeach
                </div>

                <div class="nw-pb-tabs__content">
                    @foreach ($items as $item)
                        <div class="nw-pb-tabs__pane"
                             data-tab-content="{{ $uniqueBlockId }}_{{ $item['id'] }}"
                            @if ($item['id'] === $activeTabId) data-active @else hidden @endif>

                            <div class="pb-text__content">
                                {!! $item['content'] !!}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
