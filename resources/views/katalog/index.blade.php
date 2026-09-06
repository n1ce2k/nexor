{{--
    Страница инфоблока «Каталог».

    Файл создан автоматически при включении переключателя «Создать страницу».
    Дальше он ваш: правьте разметку как угодно, CMS его больше не трогает.

    Доступно из коробки:
        \Nexor\Cms\Support\Site::iblock('katalog')            — сам инфоблок
        \Nexor\Cms\Support\Site::elements('katalog', 20)      — активные элементы
        \Nexor\Cms\Support\Site::element('katalog', 'code')   — один элемент по коду
        $element->property('CODE')                            — значение свойства
--}}

@extends('layouts.site')

@php
    $iblock = \Nexor\Cms\Support\Site::iblock('katalog');
    $elements = \Nexor\Cms\Support\Site::elements('katalog', 20);
@endphp

@section('title', $iblock?->name ?? 'Каталог')

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <header class="mb-10">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                {{ $iblock?->name ?? 'Каталог' }}
            </h1>

            @if ($iblock?->description)
                <p class="mt-3 max-w-2xl text-lg text-slate-600">{{ $iblock->description }}</p>
            @endif
        </header>

        {{-- Пример вывода элементов инфоблока. --}}
        @forelse ($elements as $element)
            <article class="mb-6 rounded-2xl border border-slate-200 p-6 transition hover:border-brand-300">
                <h2 class="text-lg font-semibold text-slate-900">{{ $element->name }}</h2>

                @if ($element->preview_text)
                    <p class="mt-2 text-sm text-slate-600">{{ $element->preview_text }}</p>
                @endif

                {{-- Свойства выводятся по символьному коду:
                     {{ $element->property('PRICE') }} --}}
            </article>
        @empty
            <p class="text-slate-500">
                {{ mb_strtolower('Товары') }} пока не добавлены — загляните в панель управления.
            </p>
        @endforelse
    </section>
@endsection
