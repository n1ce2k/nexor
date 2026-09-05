@props(['name' => null, 'value' => 1, 'checked' => false, 'label' => null, 'hint' => null, 'hidden' => true])

<label class="flex cursor-pointer items-start gap-2.5 select-none">
    @if ($name && $hidden)
        <input type="hidden" name="{{ $name }}" value="0">
    @endif

    <input type="checkbox"
           @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
           value="{{ $value }}"
           @checked($checked)
           {{ $attributes->merge([
               'class' => 'mt-0.5 size-4 shrink-0 rounded border-[var(--surface-border-strong)] bg-[var(--surface-panel)] text-brand-600 focus:ring-2 focus:ring-brand-500/40',
           ]) }}>

    @if ($label || $slot->isNotEmpty())
        <span class="text-sm">
            <span class="font-medium text-[var(--text-strong)]">{{ $label ?? $slot }}</span>
            @if ($hint)
                <span class="mt-0.5 block text-xs font-normal text-[var(--text-muted)]">{{ $hint }}</span>
            @endif
        </span>
    @endif
</label>
