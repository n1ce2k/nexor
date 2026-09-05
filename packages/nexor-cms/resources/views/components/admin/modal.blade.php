@props(['title' => null, 'maxWidth' => 'max-w-lg', 'show' => 'open'])

<div x-show="{{ $show }}"
     x-cloak
     x-trap.noscroll="{{ $show }}"
     @keydown.escape.window="{{ $show }} = false"
     class="fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div x-show="{{ $show }}"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="{{ $show }} = false"
         class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

    <div x-show="{{ $show }}"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-2 sm:scale-95"
         {{ $attributes->merge(['class' => "surface relative w-full {$maxWidth} rounded-2xl border shadow-2xl"]) }}>
        @if ($title)
            <header class="flex items-center justify-between gap-4 border-b border-[var(--surface-border)] px-5 py-4">
                <h2 class="text-sm font-semibold text-[var(--text-strong)]">{{ $title }}</h2>
                <button type="button" @click="{{ $show }} = false"
                        class="rounded-lg p-1 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)]">
                    <x-nexor::admin.icon name="x" class="size-4" />
                </button>
            </header>
        @endif

        <div class="px-5 py-4">{{ $slot }}</div>

        @isset($footer)
            <footer class="flex items-center justify-end gap-2 border-t border-[var(--surface-border)] px-5 py-4">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
