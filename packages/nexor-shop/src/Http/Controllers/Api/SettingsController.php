<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Cms\Models\ModuleState;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Enums\AddedFeedback;
use Nexor\Shop\Enums\CartDisplay;
use Nexor\Shop\Enums\CartEdition;
use Nexor\Shop\Http\Requests\SettingsRequest;
use Nexor\Shop\Support\Shop;
use Nexor\Shop\Support\TelegramNotifier;
use RuntimeException;

/**
 * Страница «Корзина»: уровень, вид, валюта с курсами и уведомления.
 */
class SettingsController extends ApiController
{
    public function show(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function update(SettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        Nexor::modules()->updateSettings(Shop::MODULE, [
            'edition' => $data['edition'],
            'display' => $data['display'],
            'open_on_add' => (bool) ($data['open_on_add'] ?? true),
            'added_feedback' => $data['added_feedback'] ?? AddedFeedback::Button->value,
            'currency' => $data['currency'],
            // Курсы вводят к той валюте, что выбрана сейчас.
            'rates' => collect($data['rates'] ?? [])
                ->filter(fn ($rate, $code) => $code !== $data['currency'] && is_numeric($rate) && $rate > 0)
                ->map(fn ($rate) => (float) $rate)
                ->all(),
            'rates_base' => $data['currency'],
            'notify_admin' => (bool) ($data['notify_admin'] ?? false),
            'admin_email' => (string) ($data['admin_email'] ?? ''),
            'notify_customer' => (bool) ($data['notify_customer'] ?? false),
            'telegram_enabled' => (bool) ($data['telegram_enabled'] ?? false),
            'telegram_chat_id' => trim((string) ($data['telegram_chat_id'] ?? '')),
            'telegram_token' => $this->tokenToStore($data['telegram_token'] ?? null),
        ]);

        if ($state = ModuleState::query()->where('code', Shop::MODULE)->first()) {
            ActivityLogger::updated($state, 'Настройки корзины');
        }

        return response()->json($this->payload() + ['message' => 'Настройки корзины сохранены.']);
    }

    /**
     * Проверочное сообщение в Telegram — теми данными, что сейчас в форме.
     */
    public function testTelegram(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telegram_token' => ['nullable', 'string', 'max:100'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64'],
        ]);

        $token = $data['telegram_token'] ?? '';
        $token = $token === '' || $token === TelegramNotifier::MASK ? TelegramNotifier::token() : $token;
        $chatId = trim((string) ($data['telegram_chat_id'] ?? '')) ?: TelegramNotifier::chatId();

        try {
            TelegramNotifier::send('✅ Проверка связи: уведомления о заказах с сайта будут приходить сюда.', $token, $chatId);
        } catch (RuntimeException $exception) {
            return $this->refuse($exception->getMessage());
        }

        return $this->ok('Сообщение отправлено — проверьте Telegram.');
    }

    /**
     * Маска — оставить сохранённый токен, пусто — удалить, иначе зашифровать новый.
     */
    protected function tokenToStore(?string $submitted): string
    {
        $submitted = trim((string) $submitted);

        if ($submitted === TelegramNotifier::MASK) {
            return (string) (Shop::settings()['telegram_token'] ?? '');
        }

        return $submitted === '' ? '' : TelegramNotifier::encrypt($submitted);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $settings = Shop::settings();

        return [
            'settings' => [
                'edition' => $settings['edition'],
                'display' => $settings['display'],
                'open_on_add' => (bool) $settings['open_on_add'],
                'added_feedback' => $settings['added_feedback'],
                'currency' => $settings['currency'],
                // Курсы к другой валюте здесь бессмысленны — отдаём пустыми.
                'rates' => ($settings['rates_base'] ?? null) === $settings['currency'] ? (object) $settings['rates'] : (object) [],
                'notify_admin' => (bool) $settings['notify_admin'],
                'admin_email' => (string) $settings['admin_email'],
                'notify_customer' => (bool) $settings['notify_customer'],
                'telegram_enabled' => (bool) $settings['telegram_enabled'],
                'telegram_chat_id' => (string) $settings['telegram_chat_id'],
                // Сам токен в браузер не уходит никогда — только маска, что он задан.
                'telegram_token' => TelegramNotifier::token() ? TelegramNotifier::MASK : '',
            ],
            'effective_edition' => Shop::edition()->value,
            'editions' => array_map(fn (CartEdition $edition) => [
                'value' => $edition->value,
                'label' => $edition->label(),
                'hint' => $edition->hint(),
                'available' => Nexor::feature($edition->feature()),
            ], CartEdition::cases()),
            'displays' => array_map(fn (CartDisplay $display) => [
                'value' => $display->value,
                'label' => $display->label(),
                'hint' => $display->hint(),
            ], CartDisplay::cases()),
            'feedbacks' => array_map(fn (AddedFeedback $feedback) => [
                'value' => $feedback->value,
                'label' => $feedback->label(),
                'hint' => $feedback->hint(),
            ], AddedFeedback::cases()),
            'currencies' => Currency::options(),
            'urls' => [
                'cart' => Shop::cartUrl(),
                'checkout' => Shop::checkoutEnabled() ? Shop::checkoutUrl() : null,
            ],
        ];
    }
}
