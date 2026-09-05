@props(['name' => null, 'checked' => false, 'label' => null, 'hint' => null])

<label class="flex cursor-pointer items-center justify-between gap-4 select-none"
       x-data="{ on: @js((bool) $checked) }">
    <span class="text-sm">
        @if ($label)
            <span class="font-medium text-[var(--text-strong)]">{{ $label }}</span>
        @endif
        @if ($hint)
            <span class="mt-0.5 block text-xs font-normal text-[var(--text-muted)]">{{ $hint }}</span>
        @endif
    </span>

    <span class="relative inline-flex shrink-0">
        @if ($name)
            <input type="hidden" name="{{ $name }}" value="0">
        @endif

        <input type="checkbox"
               @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
               value="1"
               x-model="on"
               class="peer sr-only"
               {{ $attributes }}>

        <span class="h-6 w-11 rounded-full bg-[var(--surface-border-strong)] transition peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40 peer-focus-visible:ring-offset-2"></span>
        <span class="pointer-events-none absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
    </span>
</label>
