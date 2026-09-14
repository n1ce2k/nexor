{{-- Приходит: $count, $offcanvas, $cartUrl. --}}

<div class="relative">
    @if ($offcanvas)
        <button type="button" x-data @click="$dispatch('cart-open')" title="Корзина"
                class="relative rounded-lg p-2 text-slate-700 transition hover:bg-slate-100">
            @include('nexor-shop::partials.cart-icon')
        </button>
    @else
        <a href="{{ $cartUrl }}" title="Корзина" class="relative block rounded-lg p-2 text-slate-700 transition hover:bg-slate-100">
            @include('nexor-shop::partials.cart-icon')
        </a>
    @endif
    @include('nexor-shop::partials.tab-sync')
</div>
