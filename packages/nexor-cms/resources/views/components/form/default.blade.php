{{--
    Шаблон компонента form.

    Приходит: $formFields (описания полей), $formAction, $formConfig
    (зашифрованные настройки), $sent, $sentMessage, а также пропы
    $title, $button, $consent, $name.

    Свой шаблон: php artisan nexor:component form my_form
--}}

<div class="rounded-2xl border border-slate-200 p-6">
    @if ($title)
        <h2 class="mb-4 text-xl font-semibold text-slate-900">{{ $title }}</h2>
    @endif

    @if ($sent)
        <p class="rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ $sentMessage }}</p>
    @else
        <form method="post" action="{{ $formAction }}" class="space-y-4">
            @csrf

            <input type="hidden" name="_form" value="{{ $formConfig }}">

            {{-- Ловушка для ботов: людям это поле не видно и не доступно с клавиатуры. --}}
            <div class="absolute h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>
                    Не заполняйте это поле
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </label>
            </div>

            @foreach ($formFields as $field)
                <div>
                    <label for="{{ $name }}-{{ $field['code'] }}"
                           class="mb-1.5 block text-sm font-medium text-slate-900">
                        {{ $field['label'] }}
                        @if ($field['required'])
                            <span class="text-red-500">*</span>
                        @endif
                    </label>

                    @if ($field['type'] === 'textarea')
                        <textarea id="{{ $name }}-{{ $field['code'] }}" name="{{ $field['code'] }}" rows="5"
                                  @required($field['required'])
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $field['value'] }}</textarea>
                    @else
                        <input id="{{ $name }}-{{ $field['code'] }}" type="{{ $field['type'] }}"
                               name="{{ $field['code'] }}" value="{{ $field['value'] }}"
                               @required($field['required'])
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @endif

                    @error($field['code'])
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            @if ($consent)
                <div>
                    <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="consent" value="1" @checked(old('consent'))
                               class="mt-0.5 size-4 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500/40">
                        {{ $consent }}
                    </label>

                    @error('consent')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <button type="submit"
                    class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-700">
                {{ $button }}
            </button>
        </form>
    @endif
</div>
