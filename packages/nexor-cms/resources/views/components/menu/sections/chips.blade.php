{{--
    Шаблон компонента menu.sections: чипсы в строку.

    Вложенность игнорируется намеренно — в строку помещается один уровень.
--}}

@if ($items)
    <div class="mb-8 flex flex-wrap gap-2">
        <a href="{{ url('/'.$block->code) }}"
           class="rounded-full border px-4 py-1.5 text-sm transition {{ request()->query('section') ? 'border-slate-200 text-slate-600 hover:border-brand-300' : 'border-brand-600 bg-brand-50 text-brand-700' }}">
            Все
        </a>

        @foreach ($items as $item)
            <a href="{{ $item['url'] }}"
               class="rounded-full border px-4 py-1.5 text-sm transition {{ $item['active'] ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:border-brand-300' }}">
                {{ $item['name'] }}
            </a>
        @endforeach
    </div>
@endif
