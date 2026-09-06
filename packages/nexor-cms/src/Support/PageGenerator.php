<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\File;
use Nexor\Cms\Enums\PaginationTemplate;
use Nexor\Cms\Models\Iblock;

/**
 * Turns an infoblock into a page, the way a folder is a page in Bitrix.
 *
 * Creating the infoblock with the "создать страницу" switch on scaffolds a
 * folder under `resources/views` with three files:
 *
 *   index.blade.php       listing, served at /<code>
 *   detail.blade.php      one element, served at /<code>/<element code>
 *   pagination.blade.php  the paging control the listing includes
 *
 * Files are written once and never overwritten — afterwards they belong to
 * whoever edits them.
 */
class PageGenerator
{
    /** @var array<int, string> */
    public const FILES = ['index', 'detail', 'pagination'];

    /**
     * Directory the page of an infoblock lives in, relative to resources/views.
     */
    public static function directory(Iblock $iblock): string
    {
        $base = trim((string) config('nexor.pages.directory', ''), '/');

        return $base === '' ? $iblock->code : $base.'/'.$iblock->code;
    }

    public static function path(Iblock $iblock, string $file = 'index'): string
    {
        return resource_path('views/'.self::directory($iblock).'/'.$file.'.blade.php');
    }

    /**
     * Dotted view name, ready for `view()` and `@include`.
     */
    public static function view(Iblock $iblock, string $file = 'index'): string
    {
        return str_replace('/', '.', self::directory($iblock)).'.'.$file;
    }

    public static function exists(Iblock $iblock, string $file = 'index'): bool
    {
        return File::exists(self::path($iblock, $file));
    }

    /**
     * Write whichever of the three files are missing.
     *
     * @return string|null Path of the listing, relative to resources/views.
     */
    public static function create(Iblock $iblock): ?string
    {
        foreach (self::FILES as $file) {
            $path = self::path($iblock, $file);

            if (File::exists($path)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($path));
            File::put($path, self::stub($iblock, $file));
        }

        return self::directory($iblock).'/index.blade.php';
    }

    /**
     * Rewrite only the paging control, after the template choice changed.
     */
    public static function refreshPagination(Iblock $iblock): void
    {
        if (! self::exists($iblock)) {
            return;
        }

        File::put(self::path($iblock, 'pagination'), self::stub($iblock, 'pagination'));
    }

    protected static function stub(Iblock $iblock, string $file): string
    {
        return match ($file) {
            'detail' => self::detailStub($iblock),
            'pagination' => self::paginationStub($iblock),
            default => self::indexStub($iblock),
        };
    }

    /**
     * Listing: paged elements plus the paging control.
     */
    protected static function indexStub(Iblock $iblock): string
    {
        $code = $iblock->code;
        $name = $iblock->name;
        $elements = mb_strtolower($iblock->type?->elements_name ?? 'Элементы');
        $pagination = self::view($iblock, 'pagination');

        return <<<BLADE
{{--
    Список инфоблока «{$name}».

    Файл создан автоматически и больше CMS не трогается — правьте как угодно.

    Доступно из коробки:
        \Nexor\Cms\Support\Site::iblock('{$code}')        — сам инфоблок
        \Nexor\Cms\Support\Site::paginate('{$code}')      — элементы постранично
        \Nexor\Cms\Support\Site::elements('{$code}', 20)  — просто список
        \$element->property('CODE')                        — значение свойства
        \$element->url()                                   — ссылка на деталку
--}}

@extends('site.layout')

@php
    \$iblock = \Nexor\Cms\Support\Site::iblock('{$code}');
    \$elements = \Nexor\Cms\Support\Site::paginate('{$code}');
@endphp

@section('title', \$iblock?->name ?? '{$name}')
@section('description', \$iblock?->description)

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <header class="mb-10">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                {{ \$iblock?->name ?? '{$name}' }}
            </h1>

            @if (\$iblock?->description)
                <div class="mt-3 max-w-2xl text-lg text-slate-600">{!! \$iblock->description !!}</div>
            @endif
        </header>

        {{-- Контейнер обязателен: в него кнопка «Показать ещё» дописывает следующую порцию. --}}
        <div id="nexor-items" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse (\$elements as \$element)
                <a href="{{ \$element->url() }}"
                   class="group flex flex-col rounded-2xl border border-slate-200 p-6 transition hover:border-brand-300 hover:shadow-md">
                    @if (\$element->preview_picture_url)
                        <img src="{{ \$element->preview_picture_url }}" alt="{{ \$element->name }}"
                             class="mb-4 h-40 w-full rounded-xl object-cover">
                    @endif

                    <h2 class="text-lg font-semibold text-slate-900 transition group-hover:text-brand-600">
                        {{ \$element->name }}
                    </h2>

                    @if (\$element->preview_text)
                        <p class="mt-2 text-sm text-slate-600">{{ Str::limit(strip_tags(\$element->preview_text), 140) }}</p>
                    @endif

                    {{-- Свойства выводятся по символьному коду:
                         {{ \$element->property('PRICE') }} --}}
                </a>
            @empty
                <p class="text-slate-500">{$elements} пока не добавлены — загляните в панель управления.</p>
            @endforelse
        </div>

        @include('{$pagination}', ['paginator' => \$elements, 'iblock' => \$iblock])
    </section>
@endsection

BLADE;
    }

    /**
     * Detail page of one element.
     */
    protected static function detailStub(Iblock $iblock): string
    {
        $code = $iblock->code;
        $name = $iblock->name;

        return <<<BLADE
{{--
    Детальная страница элемента инфоблока «{$name}».

    В шаблон приходит переменная \$element. Свойства читаются по коду:
        \$element->property('PRICE')
--}}

@extends('site.layout')

@section('title', \$element->meta_title ?: \$element->name)
@section('description', \$element->meta_description ?: Str::limit(strip_tags((string) \$element->preview_text), 160))

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-14 sm:px-6 lg:px-8">
        <nav class="mb-6 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
            <a href="{{ route('home') }}" class="transition hover:text-brand-600">Главная</a>
            <span>/</span>
            <a href="{{ url('/{$code}') }}" class="transition hover:text-brand-600">{{ \$element->iblock->name }}</a>
            <span>/</span>
            <span class="text-slate-700">{{ \$element->name }}</span>
        </nav>

        <header class="mb-8">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">{{ \$element->name }}</h1>
        </header>

        @if (\$element->detail_picture_url)
            <img src="{{ \$element->detail_picture_url }}" alt="{{ \$element->name }}"
                 class="mb-8 w-full rounded-2xl object-cover">
        @endif

        @if (\$element->preview_text)
            <p class="mb-8 border-l-4 border-brand-200 pl-4 text-lg text-slate-600">{{ \$element->preview_text }}</p>
        @endif

        <div class="space-y-4 leading-relaxed text-slate-700">
            @if (\$element->detail_text_type === 'html')
                {!! \$element->detail_text !!}
            @else
                {!! nl2br(e(\$element->detail_text)) !!}
            @endif
        </div>

        {{-- Свойства элемента:
        <dl class="mt-8 grid gap-2 text-sm">
            @foreach (\$element->iblock->properties as \$property)
                <div class="flex justify-between gap-4 border-b border-slate-100 py-2">
                    <dt class="text-slate-500">{{ \$property->name }}</dt>
                    <dd class="text-slate-900">{{ \$element->displayValue(\$property) }}</dd>
                </div>
            @endforeach
        </dl>
        --}}
    </article>
@endsection

BLADE;
    }

    protected static function paginationStub(Iblock $iblock): string
    {
        return match ($iblock->pagination_template) {
            PaginationTemplate::Full => self::paginationFullStub(),
            PaginationTemplate::ButtonLoad => self::paginationButtonStub(),
            default => self::paginationSimpleStub(),
        };
    }

    /**
     * Button-driven paging, shared by every template.
     *
     * The link is a real `?page=N` URL, so the listing still works without
     * JavaScript; the script only upgrades it to append in place.
     *
     * @param  string  $condition  Blade expression guarding the whole block.
     */
    protected static function loadMoreBlock(string $condition): string
    {
        $body = <<<'BLADE'
    <div class="mt-10 flex flex-col items-center gap-3" data-nexor-loadmore>
        <a href="{{ $paginator->nextPageUrl() }}" rel="next"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-6 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            Показать ещё
        </a>

        <p class="text-xs text-slate-500">
            Показано {{ $paginator->lastItem() ?? 0 }} из {{ $paginator->total() }}
        </p>
    </div>

    @once
        @push('scripts')
            <script>
                /* Дописывает следующую страницу в #nexor-items вместо перехода. */
                document.addEventListener('click', async (event) => {
                    const link = event.target.closest('[data-nexor-loadmore] a');

                    if (!link) {
                        return;
                    }

                    event.preventDefault();

                    const block = link.closest('[data-nexor-loadmore]');
                    const list = document.querySelector('#nexor-items');

                    if (!list) {
                        return;
                    }

                    const label = link.textContent;

                    link.textContent = 'Загружаем…';

                    try {
                        const html = await fetch(link.href, { headers: { 'X-Requested-With': 'fetch' } })
                            .then((response) => response.text());

                        const page = new DOMParser().parseFromString(html, 'text/html');

                        list.append(...page.querySelectorAll('#nexor-items > *'));

                        const next = page.querySelector('[data-nexor-loadmore]');

                        next ? block.replaceWith(next) : block.remove();

                        history.replaceState(null, '', link.href);
                    } catch (error) {
                        link.textContent = label;
                    }
                });
            </script>
        @endpush
    @endonce
BLADE;

        return '@if ('.$condition.')'."\n".$body."\n".'@endif';
    }

    /**
     * On a numbered template the button only shows when the switch is on.
     */
    protected static function loadMoreWhenEnabled(): string
    {
        return self::loadMoreBlock('($iblock ?? null)?->has_load_more && $paginator->hasMorePages()');
    }

    protected static function paginationSimpleStub(): string
    {
        $loadMore = self::loadMoreWhenEnabled();

        return <<<BLADE
{{--
    Шаблон пагинации: pagination — номера страниц со стрелками.

    Ожидает \$paginator и \$iblock. Подключается из index.blade.php:
        @include('…pagination', ['paginator' => \$elements, 'iblock' => \$iblock])
--}}

@if (\$paginator->hasPages())
    <nav class="mt-10 flex items-center justify-center gap-1" aria-label="Страницы">
        @if (\$paginator->onFirstPage())
            <span class="flex size-9 items-center justify-center rounded-lg text-slate-300">‹</span>
        @else
            <a href="{{ \$paginator->previousPageUrl() }}" rel="prev"
               class="flex size-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100">‹</a>
        @endif

        @foreach (\$paginator->onEachSide(1)->linkCollection()->slice(1, -1) as \$link)
            @if (\$link['label'] === '...')
                <span class="px-2 text-slate-400">…</span>
            @elseif (\$link['active'])
                <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-semibold text-white">
                    {{ \$link['label'] }}
                </span>
            @else
                <a href="{{ \$link['url'] }}"
                   class="flex size-9 items-center justify-center rounded-lg text-sm text-slate-600 transition hover:bg-slate-100">
                    {{ \$link['label'] }}
                </a>
            @endif
        @endforeach

        @if (\$paginator->hasMorePages())
            <a href="{{ \$paginator->nextPageUrl() }}" rel="next"
               class="flex size-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100">›</a>
        @else
            <span class="flex size-9 items-center justify-center rounded-lg text-slate-300">›</span>
        @endif
    </nav>
@endif

{$loadMore}

BLADE;
    }

    protected static function paginationFullStub(): string
    {
        $loadMore = self::loadMoreWhenEnabled();

        return <<<BLADE
{{--
    Шаблон пагинации: pagination_full — номера, переходы в начало и конец,
    подпись «показано N из M».
--}}

@if (\$paginator->total() > 0)
    <div class="mt-10 flex flex-col items-center gap-4">
        @if (\$paginator->hasPages())
            <nav class="flex items-center gap-1" aria-label="Страницы">
                <a href="{{ \$paginator->url(1) }}"
                   class="flex h-9 items-center rounded-lg px-3 text-sm text-slate-600 transition hover:bg-slate-100 {{ \$paginator->onFirstPage() ? 'pointer-events-none text-slate-300' : '' }}">
                    « Первая
                </a>

                @foreach (\$paginator->onEachSide(2)->linkCollection()->slice(1, -1) as \$link)
                    @if (\$link['label'] === '...')
                        <span class="px-2 text-slate-400">…</span>
                    @elseif (\$link['active'])
                        <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-semibold text-white">
                            {{ \$link['label'] }}
                        </span>
                    @else
                        <a href="{{ \$link['url'] }}"
                           class="flex size-9 items-center justify-center rounded-lg text-sm text-slate-600 transition hover:bg-slate-100">
                            {{ \$link['label'] }}
                        </a>
                    @endif
                @endforeach

                <a href="{{ \$paginator->url(\$paginator->lastPage()) }}"
                   class="flex h-9 items-center rounded-lg px-3 text-sm text-slate-600 transition hover:bg-slate-100 {{ \$paginator->currentPage() === \$paginator->lastPage() ? 'pointer-events-none text-slate-300' : '' }}">
                    Последняя »
                </a>
            </nav>
        @endif

        <p class="text-sm text-slate-500">
            Показано {{ \$paginator->firstItem() ?? 0 }}–{{ \$paginator->lastItem() ?? 0 }} из {{ \$paginator->total() }}
        </p>
    </div>
@endif

{$loadMore}

BLADE;
    }

    protected static function paginationButtonStub(): string
    {
        $loadMore = self::loadMoreBlock('$paginator->hasMorePages()');

        return <<<BLADE
{{--
    Шаблон пагинации: pagination_btnload — только кнопка «Показать ещё».

    Работает и без JavaScript: кнопка остаётся обычной ссылкой на ?page=N.
--}}

{$loadMore}

BLADE;
    }
}
