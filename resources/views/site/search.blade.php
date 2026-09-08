{{--
    Страница поиска. Собрана из компонентов: форма и результаты.

    Свои шаблоны: php artisan nexor:component search.page my_template
--}}

@extends('site.layout')

@section('title', request('q') ? 'Поиск: '.request('q') : 'Поиск')

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
        <h1 class="mb-6 text-3xl font-semibold tracking-tight text-slate-900">Поиск</h1>

        <div class="mb-10">
            <x-nexor::search.form />
        </div>

        <x-nexor::search.page />
    </section>
@endsection
