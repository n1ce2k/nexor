{{-- Приходит: $number. --}}

<div class="rounded-2xl border border-green-200 bg-green-50 p-6 text-center">
    <p class="text-lg font-semibold text-green-800">Спасибо! Заказ №{{ $number }} оформлен.</p>
    <p class="mt-2 text-sm text-green-700">Мы свяжемся с вами для подтверждения.</p>
    <a href="{{ url('/') }}" class="mt-4 inline-block text-sm font-medium text-green-800 underline underline-offset-2">
        Вернуться на главную
    </a>
</div>
