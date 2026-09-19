{{--
    Итог оплаты заказа.

    Страница показывается после возврата с платёжной страницы ЮKassa. Состояние
    к этому моменту уже уточнено у провайдера — редиректу мы не доверяем.

    Свой вид: скопируйте файл в resources/views/vendor/nexor-shop/pages.
--}}

@extends(config('nexor-shop.layout', 'site.layout'))

@section('title', $paid ? 'Заказ оплачен' : 'Оплата заказа')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-12">
        @if ($paid)
            <h1 class="text-2xl font-semibold text-slate-900">Заказ №{{ $order->number }} оплачен</h1>
            <p class="mt-3 text-slate-600">
                Спасибо! Мы получили оплату на {{ \Nexor\Shop\Support\Shop::format((float) $order->total) }}
                и уже начали собирать заказ. Чек придёт на указанный вами контакт.
            </p>
        @else
            <h1 class="text-2xl font-semibold text-slate-900">Заказ №{{ $order->number }} ждёт оплаты</h1>

            @if ($error)
                <p class="mt-3 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $error }}</p>
            @else
                <p class="mt-3 text-slate-600">
                    Оплата пока не подтверждена. Если вы её только что сделали, подождите минуту и обновите страницу —
                    банк иногда отвечает не сразу.
                </p>
            @endif

            <a href="{{ $payUrl }}"
               class="mt-6 inline-flex rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-700">
                Оплатить заказ
            </a>
        @endif

        <p class="mt-8 text-sm text-slate-500">
            Номер заказа пригодится при обращении в поддержку: <strong>{{ $order->number }}</strong>.
        </p>
    </div>
@endsection
