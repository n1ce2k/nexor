@extends('layouts.site')

@section('title', \Nexor\Cms\Models\Setting::get('site.name', config('app.name')).' — '.\Nexor\Cms\Models\Setting::get('site.tagline'))

@section('content')
    <section class="border-b border-slate-200 bg-gradient-to-b from-slate-50 to-white">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
            <p class="mb-4 inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700">
                Проект на Laravel {{ app()->version() }}
            </p>

            <h1 class="max-w-3xl text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
                {{ \Nexor\Cms\Models\Setting::get('site.name', config('app.name')) }}
            </h1>

{{--            @if ($tagline = \Nexor\Cms\Models\Setting::get('site.tagline'))--}}
{{--                <p class="mt-5 max-w-2xl text-lg text-slate-600">{{ $tagline }}</p>--}}
{{--            @endif--}}

            <div class="mt-8 flex flex-wrap items-center gap-3">
{{--                @if ($first = $pages->first())--}}
{{--                    <a href="{{ route('page', $first->code) }}"--}}
{{--                       class="inline-flex items-center rounded-lg bg-brand-600 px-5 py-3 text-sm font-medium text-white transition hover:bg-brand-700">--}}
{{--                        {{ $first->name }}--}}
{{--                    </a>--}}
{{--                @endif--}}

                <a href="{{ route('admin.dashboard') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Панель управления
                </a>
            </div>
        </div>
    </section>

    @if ($pages->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
            <h2 class="mb-8 text-2xl font-semibold text-slate-900">Страницы сайта</h2>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($pages as $page)
                    <a href="{{ route('page', $page->code) }}"
                       class="group flex flex-col rounded-2xl border border-slate-200 p-6 transition hover:border-brand-300 hover:shadow-md">
                        @if ($page->preview_picture_url)
                            <img src="{{ $page->preview_picture_url }}" alt=""
                                 class="mb-4 h-40 w-full rounded-xl object-cover">
                        @endif

                        <h3 class="text-lg font-semibold text-slate-900 transition group-hover:text-brand-600">
                            {{ $page->name }}
                        </h3>

                        @if ($subtitle = $page->property('subtitle'))
                            <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
                        @endif

                        @if ($page->preview_text)
                            <p class="mt-3 text-sm text-slate-600">{{ Str::limit($page->preview_text, 120) }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

{{--    <section class="border-t border-slate-200 bg-slate-50">--}}
{{--        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">--}}
{{--            <h2 class="mb-2 text-2xl font-semibold text-slate-900">Структура данных</h2>--}}
{{--            <p class="mb-8 text-sm text-slate-600">--}}
{{--                Содержимое сайта хранится в инфоблоках — их состав и свойства настраиваются в панели управления.--}}
{{--            </p>--}}

{{--            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">--}}
{{--                @forelse ($iblocks as $iblock)--}}
{{--                    <div class="rounded-xl border border-slate-200 bg-white p-5">--}}
{{--                        <p class="text-sm font-semibold text-slate-900">{{ $iblock->name }}</p>--}}
{{--                        <p class="mt-1 font-mono text-xs text-slate-500">{{ $iblock->code }}</p>--}}
{{--                        <p class="mt-3 text-2xl font-semibold text-brand-600">{{ $iblock->elements_count }}</p>--}}
{{--                        <p class="text-xs text-slate-500">элементов</p>--}}
{{--                    </div>--}}
{{--                @empty--}}
{{--                    <p class="text-sm text-slate-500">Инфоблоки ещё не созданы.</p>--}}
{{--                @endforelse--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </section>--}}
@endsection
