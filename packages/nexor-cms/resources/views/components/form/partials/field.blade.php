{{--
    Одно поле формы. Общая часть обычного и Livewire-шаблона.

    Приходит: $field — { code, label, type (text, textarea, tel, email, file),
    required, placeholder, accept, rows, value, inputName, errorKey, wireModel },
    $idPrefix — префикс id, чтобы две формы на странице не спорили за id,
    $wire — форма работает через Livewire (wire:model вместо name/value).
--}}

@php
    $inputId = $idPrefix.'-'.$field['code'];
    $inputClass = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none';
@endphp

<div>
    <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-slate-900">
        {{ $field['label'] }}
        @if ($field['required'])
            <span class="text-red-500">*</span>
        @endif
    </label>

    @if ($field['type'] === 'textarea')
        <textarea id="{{ $inputId }}" rows="{{ $field['rows'] }}" placeholder="{{ $field['placeholder'] }}"
                  @if ($wire) wire:model="{{ $field['wireModel'] }}" @else name="{{ $field['inputName'] }}" @endif
                  @required($field['required'])
                  class="{{ $inputClass }}">@unless ($wire){{ $field['value'] }}@endunless</textarea>
    @elseif ($field['type'] === 'file')
        <input id="{{ $inputId }}" type="file" accept="{{ $field['accept'] }}"
               @if ($wire) wire:model="{{ $field['wireModel'] }}" @else name="{{ $field['inputName'] }}" @endif
               @required($field['required'])
               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">

        @if ($wire)
            <p wire:loading wire:target="{{ $field['wireModel'] }}" class="mt-1 text-xs text-slate-500">Загружаем файл…</p>
        @endif
    @else
        <input id="{{ $inputId }}" type="{{ $field['type'] }}" placeholder="{{ $field['placeholder'] }}"
               @if ($wire) wire:model="{{ $field['wireModel'] }}" @else name="{{ $field['inputName'] }}" value="{{ $field['value'] }}" @endif
               @if ($field['type'] === 'tel') inputmode="tel" autocomplete="tel" @endif
               @if ($field['type'] === 'email') autocomplete="email" @endif
               @required($field['required'])
               class="{{ $inputClass }}">
    @endif

    @error($field['errorKey'])
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
