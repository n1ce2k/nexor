{{--
    Кнопка «В корзину».

    Приходит: $inCart (сколько уже в корзине), $cartUrl, $feedback (button|toast).

    Если корзина при добавлении не выезжает, компонент бросает браузерное событие
    `cart-added` c {elementId, name} — на него отвечает кнопка или всплывашка.

    Свой шаблон: php artisan nexor-shop:component add-to-cart
--}}

<div class="mt-4"
     x-data="{ added: false, name: '', timer: null }"
     x-on:cart-added.window="if ($event.detail.elementId === {{ $elementId }}) {
         added = true; name = $event.detail.name;
         clearTimeout(timer); timer = setTimeout(() => added = false, {{ $feedback === 'toast' ? 3500 : 2000 }});
     }">
    <div class="flex flex-wrap items-center gap-3">
        <button type="button" wire:click="add" wire:loading.attr="disabled"
                @if ($feedback === 'button')
                    :class="added ? 'bg-green-600 hover:bg-green-700' : 'bg-brand-600 hover:bg-brand-700'"
                @else
                    :class="'bg-brand-600 hover:bg-brand-700'"
                @endif
                class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition disabled:opacity-60">
            <svg wire:loading.remove wire:target="add" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
            </svg>
            <svg wire:loading wire:target="add" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z" />
            </svg>

            @if ($feedback === 'button')
                <span x-show="! added">{{ $label }}</span>
                <span x-show="added" x-cloak>Добавлено ✓</span>
            @else
                <span>{{ $label }}</span>
            @endif
        </button>

        @if ($inCart > 0)
            <a href="{{ $cartUrl }}" class="text-sm text-slate-600 hover:text-brand-600">
                В корзине: {{ rtrim(rtrim(number_format($inCart, 3, '.', ''), '0'), '.') }}
            </a>
        @endif
    </div>

    @if ($error)
        <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
    @endif

    @if ($feedback === 'toast')
        <div x-show="added" x-cloak x-transition.opacity
             class="fixed right-4 bottom-4 z-[90] flex max-w-sm items-start gap-3 rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white shadow-2xl">
            <svg class="mt-0.5 size-5 shrink-0 text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <path d="M20 6 9 17l-5-5" />
            </svg>
            <div class="min-w-0">
                <p>«<span x-text="name"></span>» добавлен в корзину</p>
                <a href="{{ $cartUrl }}" class="mt-1 inline-block font-semibold text-white underline underline-offset-2">Перейти в корзину</a>
            </div>
            <button type="button" @click="added = false" class="ml-1 text-slate-400 hover:text-white">×</button>
        </div>
    @endif
    @include('nexor-shop::partials.tab-sync')
</div>
