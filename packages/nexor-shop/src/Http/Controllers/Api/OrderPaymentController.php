<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Shop\Http\Resources\OrderResource;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\Payment;
use Nexor\Shop\Support\Payments\PaymentException;
use Nexor\Shop\Support\Payments\Payments;

/**
 * Платежи в карточке заказа: проверить состояние и вернуть деньги.
 */
class OrderPaymentController extends ApiController
{
    /**
     * Спрашивает провайдера о состоянии всех незавершённых платежей заказа.
     */
    public function sync(Order $order): JsonResponse
    {
        try {
            foreach ($order->payments()->get() as $payment) {
                Payments::sync($payment);
            }
        } catch (PaymentException $exception) {
            return $this->refuse($exception->getMessage());
        }

        Payments::refresh($order);

        return $this->respond($order->fresh(), 'Состояние платежей обновлено.');
    }

    /**
     * Возврат денег покупателю — полный или частичный.
     */
    public function refund(Request $request, Order $order, Payment $payment): JsonResponse
    {
        if ($payment->order_id !== $order->id) {
            return $this->refuse('Этот платёж относится к другому заказу.', 404);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max($payment->refundable(), 0.01)],
            'reason' => ['nullable', 'string', 'max:250'],
        ], [], ['amount' => 'сумма возврата', 'reason' => 'причина']);

        try {
            $refund = Payments::refund($payment, (float) $data['amount'], $data['reason'] ?? null, $request->user()?->id);
        } catch (PaymentException $exception) {
            return $this->refuse($exception->getMessage());
        }

        ActivityLogger::updated($order, 'Возврат '.$refund->amount.' по заказу №'.$order->number);

        return $this->respond($order->fresh(), 'Возврат отправлен в ЮKassa.');
    }

    protected function respond(Order $order, string $message): JsonResponse
    {
        return OrderResource::make($order->load(['items', 'payments.refunds']))
            ->additional(['message' => $message])
            ->response();
    }
}
