@props(['sort' => null, 'align' => 'left', 'width' => null])

@php
    $currentSort = request('sort');
    $currentDirection = request('direction', 'asc');
    $isActive = $sort && $currentSort === $sort;
    $nextDirection = $isActive && $currentDirection === 'asc' ? 'desc' : 'asc';
    $href = $sort ? request()->fullUrlWithQuery(['sort' => $sort, 'direction' => $nextDirection, 'page' => null]) : null;
@endphp

<th scope="col"
    @if ($width) style="width: {{ $width }}" @endif
    {{ $attributes->merge(['class' => 'px-4 py-3 font-medium text-'.$align.' whitespace-nowrap']) }}>
    @if ($href)
        <a href="{{ $href }}"
           class="inline-flex items-center gap-1 transition hover:text-[var(--text-strong)] {{ $isActive ? 'text-[var(--text-strong)]' : '' }}">
            {{ $slot }}
            @if ($isActive)
                <x-nexor::admin.icon :name="$currentDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3.5" />
            @else
                <x-nexor::admin.icon name="chevron-down" class="size-3.5 opacity-30" />
            @endif
        </a>
    @else
        {{ $slot }}
    @endif
</th>
