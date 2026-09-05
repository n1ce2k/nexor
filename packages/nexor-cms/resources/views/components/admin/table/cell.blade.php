@props(['align' => 'left', 'muted' => false])

<td {{ $attributes->merge([
    'class' => 'px-4 py-3 align-middle text-'.$align.' '.($muted ? 'text-[var(--text-muted)]' : 'text-[var(--text-base)]'),
]) }}>
    {{ $slot }}
</td>
