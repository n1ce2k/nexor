<?php

namespace Nexor\Shop\Support\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Nexor\Cms\Support\Secrets;
use Nexor\Shop\Enums\OrderPaymentStatus;
use Nexor\Shop\Enums\PaymentProvider;
use Nexor\Shop\Enums\PaymentStatus;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\Payment;
use Nexor\Shop\Models\PaymentMethod;
use Nexor\Shop\Models\PaymentRefund;

/**
 * Приём оплаты по заказу.
 *
 * Состояние платежа мы узнаём только запросом к провайдеру. Ни возврат
 * покупателя на страницу «спасибо», ни уведомление на вебхук деньгами не
 * являются: и то и другое — лишь повод сходить и спросить.
 */
class Payments
{
    /**
     * Настройки способа оплаты со значениями по умолчанию.
     *
     * @return array{shop_id: string, secret_key: ?string, description: string, auto_redirect: bool}
     */
    public static function settings(PaymentMethod $method): array
    {
        $stored = $method->settings ?? [];

        return [
            'shop_id' => (string) ($stored['shop_id'] ?? ''),
            'secret_key' => $stored['secret_key'] ?? null,
            'description' => (string) ($stored['description'] ?? 'Заказ №{number}'),
            // По умолчанию покупатель сам решает, когда платить: после
            // оформления он видит кнопку, а не улетает с сайта.
            'auto_redirect' => (bool) ($stored['auto_redirect'] ?? false),
        ];
    }

    /**
     * Настройки для панели: секретный ключ — только маской.
     *
     * @return array<string, mixed>
     */
    public static function forPanel(PaymentMethod $method): array
    {
        $settings = self::settings($method);

        return [...$settings, 'secret_key' => Secrets::mask($settings['secret_key'])];
    }

    /**
     * Что сохранить из панели.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function fromInput(array $input, ?array $stored): array
    {
        return [
            'shop_id' => trim((string) ($input['shop_id'] ?? '')),
            'secret_key' => Secrets::fromInput($input['secret_key'] ?? null, $stored['secret_key'] ?? null),
            'description' => trim((string) ($input['description'] ?? '')) ?: 'Заказ №{number}',
            'auto_redirect' => filter_var($input['auto_redirect'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * Уводить ли покупателя на оплату сразу после оформления.
     */
    public static function autoRedirect(?PaymentMethod $method): bool
    {
        return self::ready($method) && self::settings($method)['auto_redirect'];
    }

    /**
     * Готов ли способ принимать деньги. Без способа — разумеется, нет.
     */
    public static function ready(?PaymentMethod $method): bool
    {
        if ($method?->provider !== PaymentProvider::YooKassa) {
            return false;
        }

        $settings = self::settings($method);

        return $settings['shop_id'] !== '' && Secrets::decrypt($settings['secret_key']) !== null;
    }

    /**
     * Клиент провайдера для способа оплаты.
     *
     * @throws PaymentException
     */
    public static function client(PaymentMethod $method): YooKassa
    {
        $settings = self::settings($method);
        $secret = Secrets::decrypt($settings['secret_key']);

        if ($settings['shop_id'] === '' || $secret === null) {
            throw new PaymentException('У способа оплаты «'.$method->name.'» не заданы ключи ЮKassa.');
        }

        return new YooKassa($settings['shop_id'], $secret);
    }

    /**
     * Платёж, по которому заказ ещё можно оплатить.
     */
    public static function pending(Order $order): ?Payment
    {
        return $order->payments()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::WaitingForCapture->value])
            ->latest('id')
            ->first();
    }

    /**
     * Создаёт платёж и возвращает его; ссылка на оплату — в confirmation_url.
     *
     * Незавершённый платёж переиспользуется: две страницы оплаты на один заказ
     * сбивают с толку и покупателя, и бухгалтерию.
     *
     * @throws PaymentException
     */
    public static function start(Order $order, string $returnUrl): Payment
    {
        $method = $order->paymentMethod;

        if ($method === null || ! self::ready($method)) {
            throw new PaymentException('Для этого заказа онлайн-оплата не настроена.');
        }

        if ($order->payment_status === OrderPaymentStatus::Paid) {
            throw new PaymentException('Заказ №'.$order->number.' уже оплачен.');
        }

        if ($existing = self::pending($order)) {
            $existing = self::sync($existing);

            if ($existing->status === PaymentStatus::Pending && $existing->confirmation_url) {
                return $existing;
            }
        }

        $payment = $order->payments()->create([
            'provider' => PaymentProvider::YooKassa,
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
            'currency' => $order->currency,
            'refunded' => 0,
            'idempotence_key' => (string) Str::uuid(),
        ]);

        $body = [
            'amount' => [
                'value' => number_format((float) $order->total, 2, '.', ''),
                'currency' => $order->currency->value,
            ],
            // Одностадийная схема: деньги списываются сразу после оплаты.
            'capture' => true,
            'confirmation' => ['type' => 'redirect', 'return_url' => $returnUrl],
            'description' => self::description($method, $order),
            'metadata' => ['order_id' => (string) $order->id, 'order_number' => (string) $order->number],
        ];

        if ($receipt = Receipt::forOrder($order)) {
            $body['receipt'] = $receipt;
        }

        $response = self::client($method)->createPayment($body, $payment->idempotence_key);

        return self::apply($payment, $response);
    }

    /**
     * Перечитывает состояние платежа у провайдера.
     */
    public static function sync(Payment $payment): Payment
    {
        if ($payment->external_id === null || $payment->status->isFinal()) {
            return $payment;
        }

        $method = $payment->order?->paymentMethod;

        if ($method === null || ! self::ready($method)) {
            return $payment;
        }

        try {
            $response = self::client($method)->payment($payment->external_id);
        } catch (PaymentException $exception) {
            Log::warning('Не удалось узнать состояние платежа '.$payment->external_id.': '.$exception->getMessage());

            return $payment;
        }

        return self::apply($payment, $response);
    }

    /**
     * Возврат денег покупателю.
     *
     * @throws PaymentException
     */
    public static function refund(Payment $payment, float $amount, ?string $reason = null, ?int $userId = null): PaymentRefund
    {
        $amount = round($amount, 2);

        if ($payment->status !== PaymentStatus::Succeeded) {
            throw new PaymentException('Вернуть можно только оплаченный платёж.');
        }

        if ($amount <= 0 || $amount > $payment->refundable()) {
            throw new PaymentException('Сумма возврата должна быть больше нуля и не больше '.$payment->refundable().'.');
        }

        $method = $payment->order?->paymentMethod;

        if ($method === null || ! self::ready($method)) {
            throw new PaymentException('Способ оплаты этого заказа больше не настроен — возврат нужно сделать в личном кабинете ЮKassa.');
        }

        $refund = $payment->refunds()->create([
            'amount' => $amount,
            'status' => 'pending',
            'reason' => $reason,
            'user_id' => $userId,
        ]);

        $body = [
            'payment_id' => $payment->external_id,
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => $payment->currency->value,
            ],
        ];

        if ($reason) {
            $body['description'] = mb_substr($reason, 0, 250);
        }

        if ($receipt = Receipt::forRefund($payment->order, $amount)) {
            $body['receipt'] = $receipt;
        }

        try {
            $response = self::client($method)->createRefund($body, (string) Str::uuid());
        } catch (PaymentException $exception) {
            // Неудавшийся возврат не должен висеть в истории как ожидающий.
            $refund->delete();

            throw $exception;
        }

        $refund->forceFill([
            'external_id' => $response['id'] ?? null,
            'status' => (string) ($response['status'] ?? 'pending'),
            'payload' => $response,
        ])->save();

        if ($refund->isSucceeded()) {
            $payment->forceFill(['refunded' => round((float) $payment->refunded + $amount, 2)])->save();
            self::refresh($payment->order);
        }

        return $refund;
    }

    /**
     * Переносит ответ провайдера в платёж и заказ.
     *
     * @param  array<string, mixed>  $response
     */
    public static function apply(Payment $payment, array $response): Payment
    {
        $status = PaymentStatus::tryFrom((string) ($response['status'] ?? '')) ?? $payment->status;

        $payment->forceFill([
            'external_id' => $response['id'] ?? $payment->external_id,
            'status' => $status,
            'confirmation_url' => $response['confirmation']['confirmation_url'] ?? $payment->confirmation_url,
            'paid_at' => $status === PaymentStatus::Succeeded ? ($payment->paid_at ?? now()) : $payment->paid_at,
            'canceled_at' => $status === PaymentStatus::Canceled ? ($payment->canceled_at ?? now()) : $payment->canceled_at,
            'cancellation_reason' => $response['cancellation_details']['reason'] ?? $payment->cancellation_reason,
            'refunded' => isset($response['refunded_amount']['value'])
                ? (float) $response['refunded_amount']['value']
                : (float) ($payment->refunded ?? 0),
            'payload' => $response,
        ])->save();

        self::refresh($payment->order);

        return $payment;
    }

    /**
     * Пересчитывает состояние оплаты заказа по его платежам.
     */
    public static function refresh(?Order $order): void
    {
        if ($order === null) {
            return;
        }

        $payments = $order->payments()->get();
        $paid = $payments->firstWhere(fn (Payment $payment) => $payment->status === PaymentStatus::Succeeded);
        $refunded = $paid ? (float) $paid->refunded : 0.0;

        $status = match (true) {
            $paid !== null && $refunded >= (float) $paid->amount => OrderPaymentStatus::Refunded,
            $paid !== null && $refunded > 0 => OrderPaymentStatus::PartiallyRefunded,
            $paid !== null => OrderPaymentStatus::Paid,
            $payments->contains(fn (Payment $payment) => ! $payment->status->isFinal()) => OrderPaymentStatus::Pending,
            default => OrderPaymentStatus::Unpaid,
        };

        DB::table('shop_orders')->where('id', $order->id)->update([
            'payment_status' => $status->value,
            'paid_at' => $paid?->paid_at,
        ]);

        $order->forceFill(['payment_status' => $status, 'paid_at' => $paid?->paid_at])->syncOriginal();
    }

    /**
     * Назначение платежа: его видит покупатель в банке.
     */
    protected static function description(PaymentMethod $method, Order $order): string
    {
        $template = self::settings($method)['description'];

        return mb_substr(strtr($template, [
            '{number}' => (string) $order->number,
            '{shop}' => (string) config('app.name'),
        ]), 0, 128);
    }
}
