<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\FeedbackSubmission;
use RuntimeException;
use Throwable;

/**
 * Вкладка «Telegram» формы: заявка приходит сообщением от вашего бота.
 *
 * Сломанный Telegram заявку не теряет: запись и письмо уже есть, ошибка — в лог.
 */
class FormTelegram
{
    public const DEFAULT_MESSAGE = "📩 Новая заявка: #FORM_NAME#\n\n#ALL_FIELDS#\n\nСтраница: #PAGE_URL#\nЗапись в панели: #SUBMISSION_URL#";

    /**
     * Настройки формы со значениями по умолчанию. Токен — зашифрованный.
     *
     * @return array{enabled: bool, token: ?string, chat_ids: string, thread_id: ?int, message: string, send_files: bool, silent: bool}
     */
    public static function settings(FeedbackForm $form): array
    {
        $stored = $form->telegram ?? [];

        return [
            'enabled' => (bool) ($stored['enabled'] ?? false),
            'token' => $stored['token'] ?? null,
            'chat_ids' => (string) ($stored['chat_ids'] ?? ''),
            'thread_id' => isset($stored['thread_id']) && $stored['thread_id'] !== '' ? (int) $stored['thread_id'] : null,
            'message' => (string) (($stored['message'] ?? '') ?: self::DEFAULT_MESSAGE),
            'send_files' => (bool) ($stored['send_files'] ?? true),
            'silent' => (bool) ($stored['silent'] ?? false),
        ];
    }

    /**
     * Для панели: токен только маской.
     *
     * @return array<string, mixed>
     */
    public static function forPanel(FeedbackForm $form): array
    {
        $settings = self::settings($form);

        return [
            ...$settings,
            'token' => Secrets::mask($settings['token']),
            'default_message' => self::DEFAULT_MESSAGE,
        ];
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
        $message = trim((string) ($input['message'] ?? ''));

        return [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'token' => Secrets::fromInput($input['token'] ?? null, $stored['token'] ?? null),
            'chat_ids' => implode(', ', Telegram::chatIds($input['chat_ids'] ?? '')),
            'thread_id' => filled($input['thread_id'] ?? null) ? (int) $input['thread_id'] : null,
            // Текст по умолчанию не храним — тогда его улучшения доедут и до старых форм.
            'message' => $message === '' || $message === self::DEFAULT_MESSAGE ? null : $message,
            'send_files' => (bool) ($input['send_files'] ?? true),
            'silent' => (bool) ($input['silent'] ?? false),
        ];
    }

    public static function enabled(FeedbackForm $form): bool
    {
        $settings = self::settings($form);

        return $settings['enabled']
            && Secrets::decrypt($settings['token']) !== null
            && Telegram::chatIds($settings['chat_ids']) !== [];
    }

    /**
     * Заявка — во все указанные чаты.
     */
    public static function notify(FeedbackForm $form, FeedbackSubmission $submission): void
    {
        if (! self::enabled($form)) {
            return;
        }

        $settings = self::settings($form);
        $token = (string) Secrets::decrypt($settings['token']);
        $text = self::text($form, $submission);
        $options = ['thread_id' => $settings['thread_id'], 'silent' => $settings['silent']];
        $disk = Storage::disk(FeedbackForms::disk());

        foreach (Telegram::chatIds($settings['chat_ids']) as $chatId) {
            try {
                Telegram::sendMessage($token, $chatId, $text, $options);

                if (! $settings['send_files']) {
                    continue;
                }

                foreach ($submission->files() as $file) {
                    $stream = $disk->readStream($file['path']);

                    if ($stream === null) {
                        continue;
                    }

                    Telegram::sendDocument($token, $chatId, $stream, $file['name'], $form->name, $options);
                }
            } catch (Throwable $exception) {
                Log::error("Заявка формы «{$form->name}» не отправлена в Telegram ({$chatId}): {$exception->getMessage()}");
            }
        }
    }

    public static function text(FeedbackForm $form, FeedbackSubmission $submission): string
    {
        $text = self::settings($form)['message'];

        foreach (FeedbackForms::placeholders($form, $submission) as $key => $value) {
            $text = str_replace('#'.$key.'#', $value, $text);
        }

        // Пустые строки подряд — от незаполненных подстановок.
        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Проверочное сообщение с данными из панели, даже несохранёнными.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws RuntimeException
     */
    public static function test(array $input, ?FeedbackForm $form): void
    {
        $token = Secrets::resolve($input['token'] ?? null, $form ? self::settings($form)['token'] : null);
        $chats = Telegram::chatIds($input['chat_ids'] ?? '');

        if (! $token || $chats === []) {
            throw new RuntimeException('Укажите токен бота и chat id.');
        }

        foreach ($chats as $chatId) {
            Telegram::sendMessage(
                $token,
                $chatId,
                '✅ Проверка связи: заявки с формы «'.($form?->name ?? 'новая форма').'» будут приходить сюда.',
                ['thread_id' => filled($input['thread_id'] ?? null) ? (int) $input['thread_id'] : null],
            );
        }
    }
}
