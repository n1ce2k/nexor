{{-- Страница корзины. Макет — config('nexor-shop.layout'). --}}

@extends(config('nexor-shop.layout', 'site.layout'))

@section('title', 'Корзина')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <h1 class="mb-8 text-3xl font-semibold tracking-tight text-slate-900">Корзина</h1>

        <livewire:nexor-shop::cart-page />
    </div>
@endsection
