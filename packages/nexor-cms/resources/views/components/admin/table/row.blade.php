@props(['id' => null])

<tr @if ($id) data-row-id="{{ $id }}" @endif
    {{ $attributes->merge(['class' => 'table-row transition']) }}>
    {{ $slot }}
</tr>
