@extends('site.layout')

@section('title', 'Страница не найдена')

@section('content')
    <div class="mx-auto flex max-w-xl flex-col items-center px-4 py-28 text-center sm:px-6">
        <p class="text-6xl font-semibold text-brand-600">404</p>
        <h1 class="mt-4 text-2xl font-semibold text-slate-900">Страница не найдена</h1>
        <p class="mt-2 text-slate-600">Возможно, она была удалена или адрес введён с ошибкой.</p>

        <a href="{{ route('home') }}"
           class="mt-8 inline-flex items-center rounded-lg bg-brand-600 px-5 py-3 text-sm font-medium text-white transition hover:bg-brand-700">
            На главную
        </a>
    </div>
@endsection
