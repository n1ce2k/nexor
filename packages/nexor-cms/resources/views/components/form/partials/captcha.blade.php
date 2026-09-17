{{--
    Капча формы (вкладка «Защита»). Общая часть обычного и Livewire-шаблона.

    Приходит: $captcha — { provider (yandex, google, nexor), options, errorKey,
    refreshUrl }, $idPrefix, $wire.

    Yandex и Google рисует их собственный скрипт; перед отправкой формы
    partials/captcha-script кладёт токен в скрытое поле captcha_token.
    Nexor Captcha работает и без JavaScript: картинка, код и скрытый id задачи.
--}}

@php($inputId = $idPrefix.'-captcha')

<div>
    @if ($captcha['provider'] === 'nexor')
        @php($options = $captcha['options'])

        <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-slate-900">
            Код с картинки <span class="text-red-500">*</span>
        </label>

        <div class="flex flex-wrap items-center gap-3" data-nexor-captcha-nexor>
            <img src="{{ $options['image'] }}" alt="Код с картинки" width="{{ $options['length'] * 30 + 24 }}" height="56"
                 class="h-14 rounded-lg border border-slate-200" data-nexor-captcha-image>

            @if ($wire)
                <button type="button" wire:click="refreshCaptcha" title="Показать другой код"
                        class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5" /></svg>
                </button>
            @else
                <input type="hidden" name="captcha_id" value="{{ $options['id'] }}" data-nexor-captcha-id>
                <button type="button" data-nexor-captcha-refresh="{{ $captcha['refreshUrl'] }}" title="Показать другой код"
                        class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5" /></svg>
                </button>
            @endif

            <input id="{{ $inputId }}" type="text" required autocomplete="off" spellcheck="false"
                   maxlength="{{ $options['length'] }}" @if ($options['digits']) inputmode="numeric" @endif
                   @if ($wire) wire:model="captchaAnswer" @else name="captcha_answer" @endif
                   class="w-36 rounded-lg border border-slate-300 px-3 py-2 font-mono text-base tracking-widest uppercase focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none">
        </div>
    @else
        <input type="hidden" data-nexor-captcha-token
               @if ($wire) wire:model="captchaToken" @else name="captcha_token" @endif>

        <div data-nexor-captcha-box data-provider="{{ $captcha['provider'] }}"
             data-options="{{ json_encode($captcha['options']) }}"
             @if ($wire) wire:ignore @endif></div>
    @endif

    @error($captcha['errorKey'])
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

@include('nexor::components.form.partials.captcha-script')
