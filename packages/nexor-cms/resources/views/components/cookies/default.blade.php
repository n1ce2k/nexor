{{--
    Баннер согласия на cookie и окно с категориями.

    Приходит: $settings (все настройки из панели), $links (адреса политик),
    $assets (скрипт и стиль баннера).

    Компонент выводится, только когда посетитель ещё не отвечал, поэтому
    прятать разметку стилями не нужно. Тексты приходят с подстановками:
    #POLICY# — ссылка на политику, #COOKIES# — на политику cookie,
    #SETTINGS# — ссылка, открывающая окно категорий.

    Своя вёрстка: php artisan nexor:component cookies
--}}

@php
    $link = fn (string $url, string $text) => $url
        ? '<a href="'.e($url).'" target="_blank" rel="noopener">'.e($text).'</a>'
        : e($text);

    $text = fn (string $value) => str_replace(
        ['#POLICY#', '#COOKIES#', '#SETTINGS#'],
        [
            $link($links['policy'], 'политикой обработки данных'),
            $link($links['cookies'], 'политике использования cookie'),
            '<button type="button" class="nexor-cookies__link" data-cookies-settings>'.e($settings['banner_settings']).'</button>',
        ],
        e($value),
    );

    $categories = collect(['analytics', 'marketing'])
        ->filter(fn (string $code) => $settings[$code.'_enabled'])
        ->all();
@endphp

<link rel="stylesheet" href="{{ $assets['css'] }}">

<div class="nexor-cookies" data-cookies
     data-delay="{{ $settings['delay'] }}"
     style="--nexor-cookies-accent: {{ $settings['accept_color'] }};
            --nexor-cookies-accent-text: {{ $settings['accept_text_color'] }};
            --nexor-cookies-link: {{ $settings['link_color'] }}">

    <div class="nexor-cookies__banner" data-cookies-banner role="dialog" aria-live="polite">
        <div class="nexor-cookies__body">
            <p class="nexor-cookies__title">{{ $settings['banner_title'] }}</p>
            <p class="nexor-cookies__text">{!! $text($settings['banner_text']) !!}</p>
        </div>

        <div class="nexor-cookies__actions">
            <button type="button" class="nexor-cookies__btn" data-cookies-decline>{{ $settings['banner_decline'] }}</button>
            <button type="button" class="nexor-cookies__btn nexor-cookies__btn--accent" data-cookies-accept-all>
                {{ $settings['banner_accept'] }}
            </button>
        </div>
    </div>

    <div class="nexor-cookies__overlay" data-cookies-overlay hidden></div>

    <div class="nexor-cookies__modal" data-cookies-modal role="dialog" aria-modal="true" hidden>
        <div class="nexor-cookies__modal-head">
            <p class="nexor-cookies__title">{{ $settings['modal_title'] }}</p>
            <button type="button" class="nexor-cookies__close" data-cookies-close aria-label="Закрыть">×</button>
        </div>

        <div class="nexor-cookies__modal-body">
            <p class="nexor-cookies__text">{!! $text($settings['modal_text']) !!}</p>

            <p class="nexor-cookies__subtitle">{{ $settings['modal_subtitle'] }}</p>

            <div class="nexor-cookies__category">
                <div class="nexor-cookies__category-head">
                    <input type="checkbox" id="nexor-cookies-technical" checked disabled>
                    <label for="nexor-cookies-technical">{{ $settings['technical_title'] }}</label>

                    <button type="button" class="nexor-cookies__chevron" data-cookies-toggle aria-expanded="false"
                            aria-label="Свернуть описание">
                        <svg viewBox="0 0 12 8" aria-hidden="true"><path d="M1 6.5 6 1.5l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>

                <p class="nexor-cookies__desc" hidden>{{ $settings['technical_text'] }}</p>
            </div>

            @foreach ($categories as $code)
                <div class="nexor-cookies__category">
                    <div class="nexor-cookies__category-head">
                        <input type="checkbox" id="nexor-cookies-{{ $code }}"
                               data-cookies-category="{{ $code }}" @checked($settings[$code.'_checked'])>
                        <label for="nexor-cookies-{{ $code }}">{{ $settings[$code.'_title'] }}</label>

                        <button type="button" class="nexor-cookies__chevron" data-cookies-toggle aria-expanded="false"
                                aria-label="Свернуть описание">
                            <svg viewBox="0 0 12 8" aria-hidden="true"><path d="M1 6.5 6 1.5l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>

                    <p class="nexor-cookies__desc" hidden>{{ $settings[$code.'_text'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="nexor-cookies__actions">
            <button type="button" class="nexor-cookies__btn nexor-cookies__btn--accent" data-cookies-accept-all>
                {{ $settings['modal_accept_all'] }}
            </button>

            <button type="button" class="nexor-cookies__btn nexor-cookies__btn--soft" data-cookies-accept>
                {{ $settings['modal_accept_chosen'] }}
            </button>

            {{-- «Отказаться» ссылкой и справа — но такой же доступной, как «принять». --}}
            <button type="button" class="nexor-cookies__btn nexor-cookies__btn--link" data-cookies-decline>
                {{ $settings['modal_decline'] }}
            </button>
        </div>
    </div>
</div>

<script src="{{ $assets['js'] }}"
        data-url="{{ route('nexor.cookies.consent') }}"
        data-token="{{ csrf_token() }}"></script>
