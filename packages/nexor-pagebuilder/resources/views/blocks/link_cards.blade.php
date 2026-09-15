{{--
    Блок «Ссылки / Файлы».

    Приходит: $description (HTML, очищен при сохранении), $variant (apps / docs),
    $cols (1–4), $items — [{ text, href, image { src, alt }, isDownload }],
    $cssClass, $blockId, $element.
--}}

@if (! empty($items))
    <section class="pb-block pb-link-cards pb-cards--{{ $variant }} {{ $cssClass }}">
        <div class="pb-container">
            @if ($description)
                <div class="pb-block-description">
                    {!! $description !!}
                </div>
            @endif
            <div class="pb-cards-grid pb-cards-grid--cols-{{ (int) $cols }}">
                @foreach ($items as $item)
                    @php($isDownload = $variant === 'docs' && $item['isDownload'])
                    <a href="{{ $item['href'] }}" class="pb-card-item"
                       @if ($isDownload) download @else target="_blank" rel="nofollow" @endif>
                        @if ($variant === 'apps')
                            <div class="pb-card-body">
                                <div class="pb-card-text">{!! nl2br(e($item['text'])) !!}</div>
                                @if (! empty($item['image']['src']))
                                    <div class="pb-card-image">
                                        <img src="{{ $item['image']['src'] }}"
                                             alt="{{ $item['image']['alt'] }}"
                                             loading="lazy">
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="pb-card-body">
                                <div class="pb-card-icon-wrap">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="7" y1="17" x2="17" y2="7"></line>
                                        <polyline points="7 7 17 7 17 17"></polyline>
                                    </svg>
                                </div>
                                <div class="pb-card-text-bottom">
                                    {!! nl2br(e($item['text'])) !!}
                                </div>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
