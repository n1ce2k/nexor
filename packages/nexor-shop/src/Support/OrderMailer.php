<?php

namespace Nexor\Shop\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Nexor\Cms\Mail\FormMessage;
use Nexor\Cms\Models\MailTemplate;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderItem;
use Throwable;

/**
 * Письма о новом заказе: администратору и покупателю.
 *
 * Упавшая почта не должна ронять оформление: заказ уже сохранён, а письмо —
 * всего лишь уведомление. Ошибка уходит в лог.
 */
class OrderMailer
{
    public const ADMIN_TEMPLATE = 'SHOP_ORDER_ADMIN';

    public const CUSTOMER_TEMPLATE = 'SHOP_ORDER_CUSTOMER';

    public static function send(Order $order): void
    {
        $settings = Shop::settings();
        $data = self::placeholders($order);

        if ($settings['notify_admin'] ?? true) {
            $to = ($settings['admin_email'] ?? '') ?: Setting::get('contacts.email');

            $to
                ? self::deliver($to, self::ADMIN_TEMPLATE, $data, 'Новый заказ №'.$order->number)
                : Log::warning('Заказ №'.$order->number.' оформлен, но адрес администратора не задан.');
        }

        if (($settings['notify_customer'] ?? true) && ($email = $order->customerValue('EMAIL'))) {
            self::deliver($email, self::CUSTOMER_TEMPLATE, $data, 'Ваш заказ №'.$order->number.' принят');
        }
    }

    /**
     * @return array<string, string>
     */
    public static function placeholders(Order $order): array
    {
        return [
            'number' => (string) $order->number,
            'total' => Shop::format((float) $order->total),
            'customer' => collect($order->customer)
                ->filter(fn (array $field) => filled($field['value'] ?? null))
                ->map(fn (array $field) => $field['name'].': '.$field['value'])
                ->join("\n"),
            'items' => $order->items
                ->map(fn (OrderItem $item) => $item->name.' × '.rtrim(rtrim((string) $item->quantity, '0'), '.').' '.$item->measure
                    .' = '.Shop::format((float) $item->sum))
                ->join("\n"),
            'delivery' => $order->delivery_name
                ? $order->delivery_name.' ('.Shop::format((float) $order->delivery_price).')'
                : '—',
            'payment' => $order->payment_name ?? '—',
            'url' => url(Nexor::panelBase().'/shop/orders/'.$order->id),
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    protected static function deliver(string $to, string $code, array $data, string $fallbackSubject): void
    {
        try {
            $template = MailTemplate::query()->where('code', $code)->active()->first();

            Mail::to($to)->send(new FormMessage(
                subjectLine: $template?->render('subject', $data) ?: $fallbackSubject,
                body: $template?->render('body', $data) ?: $data['items'],
                isHtml: (bool) $template?->isHtml(),
                data: $data,
            ));
        } catch (Throwable $exception) {
            Log::error('Не удалось отправить письмо о заказе: '.$exception->getMessage());
        }
    }
}
