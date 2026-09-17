<?php

namespace Nexor\Cms\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Минимальный клиент Bot API: сообщение и файл.
 *
 * Ошибки Telegram превращаются в RuntimeException с объяснением по-русски —
 * его показывает кнопка проверки в панели, а при отправке заявки он уходит в лог.
 */
class Telegram
{
    /** Лимит Telegram на длину сообщения — 4096 символов. */
    public const MESSAGE_LIMIT = 4000;

    /** Лимит Telegram на подпись к файлу. */
    public const CAPTION_LIMIT = 1000;

    /**
     * @param  array{thread_id?: int|null, silent?: bool}  $options
     *
     * @throws RuntimeException
     */
    public static function sendMessage(string $token, string $chatId, string $text, array $options = []): void
    {
        $response = Http::timeout(8)
            ->asForm()
            ->post(self::url($token, 'sendMessage'), [
                ...self::common($chatId, $options),
                'text' => mb_substr($text, 0, self::MESSAGE_LIMIT),
                'disable_web_page_preview' => 'true',
            ]);

        self::check($response);
    }

    /**
     * @param  resource|string  $contents
     * @param  array{thread_id?: int|null, silent?: bool}  $options
     *
     * @throws RuntimeException
     */
    public static function sendDocument(string $token, string $chatId, $contents, string $filename, ?string $caption = null, array $options = []): void
    {
        $response = Http::timeout(60)
            ->attach('document', $contents, $filename)
            ->post(self::url($token, 'sendDocument'), array_filter([
                ...self::common($chatId, $options),
                'caption' => $caption === null ? null : mb_substr($caption, 0, self::CAPTION_LIMIT),
            ], fn ($value) => $value !== null));

        self::check($response);
    }

    /**
     * Несколько получателей в одной строке: через запятую, точку с запятой или пробел.
     *
     * @return array<int, string>
     */
    public static function chatIds(?string $list): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[\s,;]+/', (string) $list) ?: []),
            fn (string $chat) => $chat !== '',
        )));
    }

    protected static function url(string $token, string $method): string
    {
        return 'https://api.telegram.org/bot'.$token.'/'.$method;
    }

    /**
     * @param  array{thread_id?: int|null, silent?: bool}  $options
     * @return array<string, string>
     */
    protected static function common(string $chatId, array $options): array
    {
        return array_filter([
            'chat_id' => $chatId,
            // Тема в группе с темами (форуме).
            'message_thread_id' => ! empty($options['thread_id']) ? (string) $options['thread_id'] : null,
            'disable_notification' => ! empty($options['silent']) ? 'true' : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @throws RuntimeException
     */
    protected static function check(Response $response): void
    {
        if (! $response->successful() || ! $response->json('ok')) {
            throw new RuntimeException(self::explain((string) $response->json('description', 'Telegram ответил ошибкой '.$response->status())));
        }
    }

    /**
     * Частые ответы Telegram — по-русски и с подсказкой, что делать.
     */
    public static function explain(string $description): string
    {
        return match (true) {
            str_contains($description, 'Unauthorized') => 'Telegram не принял токен бота — проверьте его в @BotFather.',
            str_contains($description, 'chat not found') => 'Чат не найден. Напишите боту /start или добавьте его в группу, затем проверьте chat id.',
            str_contains($description, 'bot was blocked') => 'Бот заблокирован в этом чате — разблокируйте его.',
            str_contains($description, 'message thread not found') => 'Тема (thread id) не найдена в этой группе.',
            str_contains($description, 'not enough rights') => 'У бота нет прав писать в этот чат.',
            default => 'Telegram: '.$description,
        };
    }
}
