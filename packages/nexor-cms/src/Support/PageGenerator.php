<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\File;
use Nexor\Cms\Models\Iblock;

/**
 * Turns an infoblock into a page, the way a folder is a page in Bitrix.
 *
 * Creating the infoblock with the "создать страницу" switch on scaffolds a
 * folder under `resources/views` with two files:
 *
 *   index.blade.php   listing, served at /<code>
 *   section.blade.php one section, served at /<code>/<section path>
 *   detail.blade.php  one element, served at /<code>/<section path>/<element>
 *
 * Both are written once and never overwritten — afterwards they belong to
 * whoever edits them. The markup inside them is not copied: they only call the
 * package's components, and those are replaced template by template with
 * `php artisan nexor:component`.
 */
class PageGenerator
{
    /** @var array<int, string> */
    public const FILES = ['index', 'section', 'detail'];

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
     * Write whichever of the files are missing.
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

    protected static function stub(Iblock $iblock, string $file): string
    {
        return match ($file) {
            'detail' => self::detailStub($iblock),
            'section' => self::sectionStub($iblock),
            default => self::indexStub($iblock),
        };
    }

    /**
     * Section page: the same components, plus the subsections of this one.
     *
     * Only scaffolded for infoblocks that use sections; without them the file
     * would never be reached.
     */
    protected static function sectionStub(Iblock $iblock): string
    {
        if (! $iblock->has_sections) {
            return self::indexStub($iblock);
        }

        $code = $iblock->code;

        return <<<BLADE
{{--
    Страница раздела инфоблока «{$iblock->name}», адрес `/{$code}/<путь раздела>`.

    В шаблон приходит \$section — открытый раздел. Компонентам его передавать
    не нужно: они узнают текущий раздел из адреса сами.

    Свой шаблон списка: php artisan nexor:component catalog.section my_template
--}}

@extends('site.layout')

@section('title', \$section->meta_title ?: \$section->name)
{{-- Не `null`: Blade понял бы это как «открыть секцию» и оставил бы буфер. --}}
@section('description', \$section->meta_description ?? '')

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <x-nexor::breadcrumbs iblock="{$code}" />

        <header class="mb-10">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                {{ \$section->name }}
            </h1>

            @if (\$section->description)
                <div class="mt-3 max-w-2xl text-lg text-slate-600">{!! \$section->description !!}</div>
            @endif
        </header>

        {{-- Подразделы этого раздела, если они есть. --}}
        <x-nexor::catalog.section-list iblock="{$code}" class="mb-10" />

        <div class="grid gap-8 lg:grid-cols-[16rem_1fr]">
            <aside class="space-y-6">
                <x-nexor::menu.sections iblock="{$code}" />
                <x-nexor::catalog.filter iblock="{$code}" />
            </aside>

            <div>
                <x-nexor::catalog.section iblock="{$code}" />
            </div>
        </div>
    </section>
@endsection

BLADE;
    }

    /**
     * Listing: filter, sections and the paged elements, each its own component.
     */
    protected static function indexStub(Iblock $iblock): string
    {
        $code = $iblock->code;
        $name = $iblock->name;

        $aside = $iblock->has_sections
            ? <<<BLADE
            <aside class="space-y-6">
                <x-nexor::menu.sections iblock="{$code}" />
                <x-nexor::catalog.filter iblock="{$code}" />
            </aside>


BLADE
            : '';

        $grid = $iblock->has_sections
            ? 'grid gap-8 lg:grid-cols-[16rem_1fr]'
            : '';

        return <<<BLADE
{{--
    Список инфоблока «{$name}».

    Файл создан автоматически и больше CMS не трогается — правьте как угодно.

    Страница только расставляет компоненты; вёрстка живёт в их шаблонах.
    Забрать шаблон себе и править:

        php artisan nexor:component catalog.section         все шаблоны
        php artisan nexor:component catalog.section blog    свой шаблон blog

    Список доступных компонентов: php artisan nexor:component
--}}

@extends('site.layout')

@php
    \$iblock = \Nexor\Cms\Support\Site::iblock('{$code}');
@endphp

@section('title', \$iblock?->name ?? '{$name}')
@section('description', \$iblock?->description ?? '')

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <x-nexor::breadcrumbs iblock="{$code}" />

        <header class="mb-10">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                {{ \$iblock?->name ?? '{$name}' }}
            </h1>

            @if (\$iblock?->description)
                <div class="mt-3 max-w-2xl text-lg text-slate-600">{!! \$iblock->description !!}</div>
            @endif
        </header>

        <div class="{$grid}">
{$aside}            <div>
                <x-nexor::catalog.section iblock="{$code}" />
            </div>
        </div>
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

    В шаблон приходят \$element и \$offer — торговое предложение из адреса
    /{$code}/…/<товар>/<предложение>, или null.

    Забрать шаблон карточки себе: php artisan nexor:component catalog.element
--}}

@extends('site.layout')

@section('title', (\$element->meta_title ?: \$element->name).(\$offer ? ' — '.\$offer->name : ''))
@section('description', \$element->meta_description ?: Str::limit(strip_tags((string) \$element->preview_text), 160))
@section('canonical', (\$offer ?? \$element)->url())

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6 lg:px-8">
        <x-nexor::breadcrumbs iblock="{$code}" :element="\$element" />

        <x-nexor::catalog.element :element="\$element" :offer="\$offer" />
    </div>

    {{-- Ещё один список на той же странице: похожие элементы. --}}
    <section class="mx-auto max-w-6xl px-4 pb-14 sm:px-6 lg:px-8">
        <h2 class="mb-4 text-xl font-semibold text-slate-900">Смотрите также</h2>

        <x-nexor::catalog.section iblock="{$code}" :paginate="false" :limit="3"
                                  :filter="['id' => ['!=', \$element->id]]" />
    </section>
@endsection

BLADE;
    }
}
