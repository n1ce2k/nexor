{{--
    Шаблон компонента form — отправка без перезагрузки (Livewire).

    Работает у формы из админки, если включено «Отправка без перезагрузки».
    Пара к default.blade.php: свой шаблон `my_form` — это my_form.blade.php и
    my_form-livewire.blade.php.

    Приходит: $formFields, $formAgreement, $formCaptcha, $formTitle, $formButton, $sent,
    $sentMessage, $idPrefix. Значения — в свойствах компонента: fields.<код>,
    agreement, website (ловушка для ботов).

    Корневой элемент должен быть один — так требует Livewire.
--}}

<div class="rounded-2xl border border-slate-200 p-6">
    @if ($formTitle)
        <h2 class="mb-4 text-xl font-semibold text-slate-900">{{ $formTitle }}</h2>
    @endif

    @if ($sent)
        <p class="rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ $sentMessage }}</p>
    @else
        <form wire:submit="submit" class="space-y-4">
            {{-- Ловушка для ботов: людям это поле не видно и не доступно с клавиатуры. --}}
            <div class="absolute h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>
                    Не заполняйте это поле
                    <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </label>
            </div>

            @foreach ($formFields as $field)
                @include('nexor::components.form.partials.field', [
                    'field' => $field,
                    'idPrefix' => $idPrefix,
                    'wire' => true,
                ])
            @endforeach

            @if ($formCaptcha)
                @include('nexor::components.form.partials.captcha', ['captcha' => $formCaptcha, 'idPrefix' => $idPrefix, 'wire' => true])
            @endif

            @if ($formAgreement)
                @include('nexor::components.form.partials.agreement', ['agreement' => $formAgreement, 'wire' => true])
            @endif

            @error('form')
                <p class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>
            @enderror

            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">{{ $formButton }}</span>
                <span wire:loading wire:target="submit">Отправляем…</span>
            </button>
        </form>
    @endif
</div>
