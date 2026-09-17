{{--
    Галочка соглашения. Общая часть обычного и Livewire-шаблона.

    Приходит: $agreement — { name, parts {before, link, after}, popup, url,
    model (App соглашение или null), modalId, checked }, $wire.

    Ссылка в подписи открывает полный текст: во всплывающем окне (popup) или на
    странице /agreement/<код> в новой вкладке. Окно работает без JavaScript —
    на CSS :target: ссылка ведёт на #id окна, крестик — прочь от него.
--}}

@php
    $parts = $agreement['parts'];
    $checkId = $agreement['modalId'].'-check';
    $linkClass = 'text-brand-600 underline underline-offset-2 hover:text-brand-700';

    // Подпись собирается одной строкой: пробелы между тегами иначе встали бы
    // перед запятой или точкой после ссылки. Пробелы по краям — снаружи
    // <label>: если label блочно-строчный, пробел внутри него пропадает.
    $label = function (string $text) use ($checkId): string {
        if (trim($text) === '') {
            return e($text);
        }

        preg_match('/^(\s*)(.*?)(\s*)$/us', $text, $match);

        return e($match[1]).'<label for="'.e($checkId).'" class="cursor-pointer">'.e($match[2]).'</label>'.e($match[3]);
    };

    $link = match (true) {
        $parts['link'] === '' => '',
        $agreement['popup'] && $agreement['model'] !== null => '<a href="#'.e($agreement['modalId']).'" class="'.$linkClass.'">'.e($parts['link']).'</a>',
        $agreement['url'] !== null => '<a href="'.e($agreement['url']).'" target="_blank" rel="noopener" class="'.$linkClass.'">'.e($parts['link']).'</a>',
        default => $label($parts['link']),
    };
@endphp

<div>
    <div class="flex items-start gap-2 text-sm text-slate-600">
        <input id="{{ $checkId }}" type="checkbox" value="1"
               @if ($wire) wire:model="{{ $agreement['name'] }}" @else name="{{ $agreement['name'] }}" @checked($agreement['checked']) @endif
               class="mt-0.5 size-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500/40">

        <p>{!! $label($parts['before']).$link.$label($parts['after']) !!}</p>
    </div>

    @error($agreement['name'])
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror

    @if ($agreement['popup'] && $agreement['model'])
        <div id="{{ $agreement['modalId'] }}" role="dialog" aria-modal="true" aria-label="{{ $agreement['model']->name }}"
             class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 target:flex">
            <a href="#_" class="absolute inset-0" aria-label="Закрыть" tabindex="-1"></a>

            <div class="relative max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h3 class="text-lg font-semibold text-slate-900">{{ $agreement['model']->name }}</h3>
                    <a href="#_" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Закрыть">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
                    </a>
                </div>

                <div class="prose-site text-sm">
                    @include('nexor::components.form.partials.agreement-text', ['agreement' => $agreement['model']])
                </div>
            </div>
        </div>
    @endif
</div>
