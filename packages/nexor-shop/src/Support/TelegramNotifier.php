<?php

namespace Nexor\Shop\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nexor\Shop\Models\Order;
use RuntimeException;
use Throwable;

/**
 * Уведомления о заказах в Telegram через бота.
 *
 * Токен бота — это пароль: в базе он зашифрован ключом приложения, в панель
 * уходит только маска. Сломанный Telegram заказ не роняет — ошибка в лог.
 */
class TelegramNotifier
{
    /** То, что панель видит вместо сохранённого токена. */
    public const MASK = '••••••••';

    public static function enabled(): bool
    {
        return (bool) (Shop::settings()['telegram_enabled'] ?? false) && self::configured();
    }

    public static function configured(): bool
    {
        return self::token() !== null && self::chatId() !== null;
    }

    public static function token(): ?string
    {
        $stored = (string) (Shop::settings()['telegram_token'] ?? '');

        if ($stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            // Сменили APP_KEY — старый токен уже не прочитать, его надо ввести заново.
            return null;
        }
    }

    public static function chatId(): ?string
    {
        $chat = trim((string) (Shop::settings()['telegram_chat_id'] ?? ''));

        return $chat === '' ? null : $chat;
    }

    public static function encrypt(string $token): string
    {
        return Crypt::encryptString($token);
    }

    /**
     * Новый заказ — в чат, если уведомления включены.
     */
    public static function sendOrder(Order $order): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            self::send(self::orderText($order));
        } catch (Throwable $exception) {
            Log::error('Не удалось отправить заказ №'.$order->number.' в Telegram: '.$exception->getMessage());
        }
    }

    /**
     * Отправляет сообщение. Бросает исключение с объяснением от Telegram —
     * для кнопки «Отправить проверочное сообщение».
     *
     * @throws RuntimeException
     */
    public static function send(string $text, ?string $token = null, ?string $chatId = null): void
    {
        $token ??= self::token();
        $chatId ??= self::chatId();

        if (! $token || ! $chatId) {
            throw new RuntimeException('Не заданы токен бота или chat id.');
        }

        $response = Http::timeout(8)
            ->asForm()
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => mb_substr($text, 0, 4000),
                'disable_web_page_preview' => 'true',
            ]);

        if (! $response->successful() || ! $response->json('ok')) {
            throw new RuntimeException(self::explain((string) $response->json('description', 'Telegram ответил ошибкой '.$response->status())));
        }
    }

    public static function orderText(Order $order): string
    {
        $data = OrderMailer::placeholders($order);

        $lines = [
            '🛒 Новый заказ №'.$data['number'],
            'Сумма: '.$data['total'],
            '',
            'Покупатель:',
            $data['customer'] ?: '—',
            '',
            'Товары:',
            $data['items'],
        ];

        if ($order->delivery_name || $order->payment_name) {
            $lines[] = '';
            $lines[] = 'Доставка: '.$data['delivery'];
            $lines[] = 'Оплата: '.$data['payment'];
        }

        if ($order->promocode_code) {
            $lines[] = 'Промокод: '.$order->promocode_code;
        }

        $lines[] = '';
        $lines[] = 'Заказ в панели: '.$data['url'];

        return implode("\n", $lines);
    }

    /**
     * Частые ответы Telegram — по-русски и с подсказкой, что делать.
     */
    protected static function explain(string $description): string
    {
        return match (true) {
            str_contains($description, 'Unauthorized') => 'Telegram не принял токен бота — проверьте его в @BotFather.',
            str_contains($description, 'chat not found') => 'Чат не найден. Напишите боту /start или добавьте его в группу, затем проверьте chat id.',
            str_contains($description, 'bot was blocked') => 'Бот заблокирован в этом чате — разблокируйте его.',
            default => 'Telegram: '.$description,
        };
    }
}
