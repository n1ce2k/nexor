@props(['head' => null])

<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="w-full min-w-full text-left text-sm">
        @isset($head)
            <thead class="table-head text-xs font-medium tracking-wide uppercase">
                <tr>{{ $head }}</tr>
            </thead>
        @endisset

        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
