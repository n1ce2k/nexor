@props(['align' => 'right', 'width' => 'w-48'])

<div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative">
    <div @click="open = ! open">{{ $trigger }}</div>

    <div x-show="open"
         x-cloak
         @click.outside="open = false"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="surface absolute z-50 mt-2 {{ $width }} origin-top-{{ $align }} rounded-xl border p-1 shadow-lg {{ $align === 'right' ? 'right-0' : 'left-0' }}">
        {{ $slot }}
    </div>
</div>
