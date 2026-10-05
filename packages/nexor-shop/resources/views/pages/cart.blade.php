{{-- Страница корзины. Макет — config('nexor-shop.layout'). --}}

@extends(config('nexor-shop.layout', 'site.layout'))

@section('title', 'Корзина')

{{-- Крошку выведет компонент крошек в макете сайта. Вызов, а не директива breadcrumb:
     модуль ставится и на ядро старше 0.3.29, где её ещё нет. --}}
@php
    if (class_exists(\Nexor\Cms\Support\CurrentPage::class)) {
        \Nexor\Cms\Support\CurrentPage::get()->crumb('Корзина');
    }
@endphp

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <h1 class="mb-8 text-3xl font-semibold tracking-tight text-slate-900">Корзина</h1>

        <livewire:nexor-shop::cart-page />
    </div>
@endsection
