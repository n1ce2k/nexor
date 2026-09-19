<?php

namespace Nexor\Shop\Http\Controllers\Site;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Nexor\Shop\Enums\OrderPaymentStatus;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\Payment;
use Nexor\Shop\Support\Payments\PaymentException;
use Nexor\Shop\Support\Payments\Payments;

/**
 * Оплата заказа покупателем.
 *
 * Ссылки подписаны (`signed`): номер заказа в адресе иначе позволил бы
 * подсмотреть чужой заказ простым перебором.
 */
class PaymentController extends Controller
{
    /**
     * Создаёт платёж и уводит покупателя на страницу оплаты.
     */
    public function pay(Request $request, Order $order): RedirectResponse
    {
        if ($order->payment_status === OrderPaymentStatus::Paid) {
            return redirect()->to($this->returnUrl($order));
        }

        try {
            $payment = Payments::start($order, $this->returnUrl($order));
        } catch (PaymentException $exception) {
            return redirect()->to($this->returnUrl($order))->with('nexor-shop.payment.error', $exception->getMessage());
        }

        if (! $payment->confirmation_url) {
            return redirect()->to($this->returnUrl($order))
                ->with('nexor-shop.payment.error', 'ЮKassa не дала ссылку на оплату. Попробуйте ещё раз.');
        }

        return redirect()->away($payment->confirmation_url);
    }

    /**
     * Покупатель вернулся с платёжной страницы.
     *
     * Самому редиректу мы не верим: состояние спрашиваем у провайдера.
     */
    public function result(Request $request, Order $order): View
    {
        if ($payment = Payments::pending($order)) {
            Payments::sync($payment);
            $order->refresh();
        }

        return view('nexor-shop::pages.payment-result', [
            'order' => $order,
            'paid' => $order->payment_status === OrderPaymentStatus::Paid,
            'payUrl' => $this->payUrl($order),
            'error' => session('nexor-shop.payment.error'),
        ]);
    }

    /**
     * Уведомление от ЮKassa.
     *
     * Тело уведомления — только повод сходить за состоянием: подделать запрос
     * может кто угодно, а вот ответить от имени ЮKassa на её же API — нет.
     */
    public function webhook(Request $request): JsonResponse
    {
        $external = (string) $request->input('object.id');
        $type = (string) $request->input('event');

        if ($external === '') {
            return response()->json(['ok' => false], 400);
        }

        $payment = Payment::query()->where('external_id', $external)->first();

        if ($payment === null && str_starts_with($type, 'refund.')) {
            $payment = Payment::query()
                ->where('external_id', (string) $request->input('object.payment_id'))
                ->first();
        }

        if ($payment !== null) {
            Payments::sync($payment);
        }

        // ЮKassa ждёт 200: иначе она будет повторять уведомление сутки.
        return response()->json(['ok' => true]);
    }

    protected function payUrl(Order $order): string
    {
        return URL::signedRoute('shop.payment.pay', ['order' => $order->id]);
    }

    protected function returnUrl(Order $order): string
    {
        return URL::signedRoute('shop.payment.result', ['order' => $order->id]);
    }
}
