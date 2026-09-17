{{--
    Шаблон компонента form — обычная отправка с перезагрузкой страницы.

    Подключение:
        <x-nexor::form :id="3" />                форма из админки («Формы ОС»)
        <x-nexor::form form="callback" />        …по символьному коду
        <x-nexor::form :fields="['name', 'phone']" title="Заказать звонок" />   простая форма
    У формы из админки с «Отправкой без перезагрузки» работает шаблон
    default-livewire.blade.php — правьте оба одинаково.

    Приходит: $formFields (поля, см. partials/field), $formAgreement (галочка
    соглашения или null, см. partials/agreement), $formCaptcha (капча или
    null, см. partials/captcha), $formTitle, $formButton,
    $formAction, $formConfig (зашифрованные настройки), $multipart (есть поле
    «файл»), $sent, $sentMessage, $formName.

    Свой шаблон: php artisan nexor:component form my_form
--}}

<div class="rounded-2xl border border-slate-200 p-6">
    @if ($formTitle)
        <h2 class="mb-4 text-xl font-semibold text-slate-900">{{ $formTitle }}</h2>
    @endif

    @if ($sent)
        <p class="rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ $sentMessage }}</p>
    @else
        <form method="post" action="{{ $formAction }}" class="space-y-4"
              @if ($multipart) enctype="multipart/form-data" @endif>
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
                @include('nexor::components.form.partials.field', [
                    'field' => $field,
                    'idPrefix' => $formName,
                    'wire' => false,
                ])
            @endforeach

            @if ($formCaptcha)
                @include('nexor::components.form.partials.captcha', ['captcha' => $formCaptcha, 'idPrefix' => $formName, 'wire' => false])
            @endif

            @if ($formAgreement)
                @include('nexor::components.form.partials.agreement', ['agreement' => $formAgreement, 'wire' => false])
            @endif

            <button type="submit"
                    class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-700">
                {{ $formButton }}
            </button>
        </form>
    @endif
</div>
