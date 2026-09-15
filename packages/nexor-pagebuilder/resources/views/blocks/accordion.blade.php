{{--
    Блок «Аккордеон».

    Приходит: $items — [{ title, content }] (content — HTML, очищен при сохранении),
    $openFirst, $cssClass, $blockId, $element.

    Раскрытие — скрипт модуля по data-accordion (или свой скрипт сайта).
--}}

@if (! empty($items))
    <section class="pb-block pb-accordion {{ $cssClass }}">
        <div class="pb-container">
            <div class="nw-accordion" data-accordion itemscope itemtype="https://schema.org/FAQPage">
                @foreach ($items as $index => $item)
                    @php($activeAttr = $openFirst && $index === 0 ? 'data-active' : '')
                    <div class="nw-accordion__item" data-accordion-item {{ $activeAttr }} itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                        <div class="nw-accordion__head" data-accordion-trigger {{ $activeAttr }}>
                            <h3 class="nw-accordion__title" itemprop="name">{{ $item['title'] }}</h3>
                            <div class="nw-accordion__icon">
                                <svg class="icon icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M18.9999 18.9999L5 5M19 5L5 19" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>
                        <div class="nw-accordion__body" data-accordion-body {{ $activeAttr }} itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                            <div class="nw-accordion__body-wrap" data-accordion-content>
                                {!! $item['content'] !!}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
