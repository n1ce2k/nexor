@extends('site.layout')

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

                <a href="/admin"
                   class="inline-flex items-center rounded-lg border border-slate-300 px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Панель управления
                </a>
            </div>
        </div>
    </section>

    <x-nexor::news.detail iblock="news" id="13" template="news1" />
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
