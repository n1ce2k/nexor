<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\File;
use Nexor\Cms\Models\Iblock;

/**
 * Turns an infoblock into a page, the way a folder is a page in Bitrix.
 *
 * Creating the infoblock with the "создать страницу" switch on scaffolds
 * `resources/views/<code>/index.blade.php` in the host application, which the
 * public route then serves at `/<code>`.
 */
class PageGenerator
{
    /**
     * Directory the page of an infoblock lives in, relative to resources/views.
     */
    public static function directory(Iblock $iblock): string
    {
        $base = trim((string) config('nexor.pages.directory', ''), '/');

        return $base === '' ? $iblock->code : $base.'/'.$iblock->code;
    }

    public static function path(Iblock $iblock): string
    {
        return resource_path('views/'.self::directory($iblock).'/index.blade.php');
    }

    public static function exists(Iblock $iblock): bool
    {
        return File::exists(self::path($iblock));
    }

    /**
     * Create the page folder and its Blade file unless one is already there.
     *
     * Never overwrites: once the file exists it belongs to whoever edits it.
     *
     * @return string|null View path relative to resources/views, or null if nothing was written.
     */
    public static function create(Iblock $iblock): ?string
    {
        $path = self::path($iblock);

        if (File::exists($path)) {
            return self::directory($iblock).'/index.blade.php';
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, self::stub($iblock));

        return self::directory($iblock).'/index.blade.php';
    }

    /**
     * Starter template: a working example of reading the infoblock it belongs to.
     */
    protected static function stub(Iblock $iblock): string
    {
        $code = $iblock->code;
        $name = $iblock->name;
        $elements = $iblock->type?->elements_name ?? 'Элементы';

        return <<<BLADE
{{--
    Страница инфоблока «{$name}».

    Файл создан автоматически при включении переключателя «Создать страницу».
    Дальше он ваш: правьте разметку как угодно, CMS его больше не трогает.

    Доступно из коробки:
        \Nexor\Cms\Support\Site::iblock('{$code}')            — сам инфоблок
        \Nexor\Cms\Support\Site::elements('{$code}', 20)      — активные элементы
        \Nexor\Cms\Support\Site::element('{$code}', 'code')   — один элемент по коду
        \$element->property('CODE')                            — значение свойства
--}}

@extends('layouts.site')

@php
    \$iblock = \Nexor\Cms\Support\Site::iblock('{$code}');
    \$elements = \Nexor\Cms\Support\Site::elements('{$code}', 20);
@endphp

@section('title', \$iblock?->name ?? '{$name}')

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <header class="mb-10">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                {{ \$iblock?->name ?? '{$name}' }}
            </h1>

            @if (\$iblock?->description)
                <p class="mt-3 max-w-2xl text-lg text-slate-600">{{ \$iblock->description }}</p>
            @endif
        </header>

        {{-- Пример вывода элементов инфоблока. --}}
        @forelse (\$elements as \$element)
            <article class="mb-6 rounded-2xl border border-slate-200 p-6 transition hover:border-brand-300">
                <h2 class="text-lg font-semibold text-slate-900">{{ \$element->name }}</h2>

                @if (\$element->preview_text)
                    <p class="mt-2 text-sm text-slate-600">{{ \$element->preview_text }}</p>
                @endif

                {{-- Свойства выводятся по символьному коду:
                     {{ \$element->property('PRICE') }} --}}
            </article>
        @empty
            <p class="text-slate-500">
                {{ mb_strtolower('{$elements}') }} пока не добавлены — загляните в панель управления.
            </p>
        @endforelse
    </section>
@endsection

BLADE;
    }
}
