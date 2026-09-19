{{-- Приходит: $number; $paymentUrl — если заказ можно оплатить онлайн. --}}

@php($paymentUrl = $paymentUrl ?? null)

<div class="rounded-2xl border border-green-200 bg-green-50 p-6 text-center">
    <p class="text-lg font-semibold text-green-800">Спасибо! Заказ №{{ $number }} оформлен.</p>

    @if ($paymentUrl)
        <p class="mt-2 text-sm text-green-700">Остался последний шаг — оплата.</p>

        <a href="{{ $paymentUrl }}"
           class="mt-4 inline-block rounded-lg bg-green-700 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-green-800">
            Оплатить заказ
        </a>

        <p class="mt-3 text-xs text-green-700">
            Если платёж не прошёл, мы свяжемся с вами — ссылка на оплату останется рабочей.
        </p>
    @else
        <p class="mt-2 text-sm text-green-700">Мы свяжемся с вами для подтверждения.</p>
    @endif

    <a href="{{ url('/') }}" class="mt-4 inline-block text-sm font-medium text-green-800 underline underline-offset-2">
        Вернуться на главную
    </a>
</div>
