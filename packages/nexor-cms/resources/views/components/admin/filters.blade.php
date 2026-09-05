@props(['action' => null, 'placeholder' => 'Поиск...'])

<form method="GET" action="{{ $action ?? request()->url() }}"
      {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3']) }}>
    <div class="relative min-w-0 flex-1 sm:max-w-xs">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[var(--text-faint)]">
            <x-nexor::admin.icon name="search" class="size-4" />
        </span>

        <input type="search" name="search" value="{{ request('search') }}"
               placeholder="{{ $placeholder }}"
               class="field-input pl-9">
    </div>

    {{ $slot }}

    <div class="flex items-center gap-2">
        <x-nexor::admin.button type="submit" variant="secondary" size="md" icon="filter">Применить</x-nexor::admin.button>

        @if (collect(request()->except('page'))->filter()->isNotEmpty())
            <x-nexor::admin.button :href="request()->url()" variant="ghost" size="md">Сбросить</x-nexor::admin.button>
        @endif
    </div>

    {{-- Keep the current ordering when a filter is applied. --}}
    @foreach (request()->only(['sort', 'direction']) as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>
