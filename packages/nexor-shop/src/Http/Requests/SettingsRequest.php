<?php

namespace Nexor\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Enums\AddedFeedback;
use Nexor\Shop\Enums\CartDisplay;
use Nexor\Shop\Enums\CartEdition;
use Nexor\Shop\Support\TelegramNotifier;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Маршрут уже проверил право shop.cart.update.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition' => ['required', Rule::enum(CartEdition::class)],
            'display' => ['required', Rule::enum(CartDisplay::class)],
            'open_on_add' => ['boolean'],
            'added_feedback' => ['nullable', Rule::enum(AddedFeedback::class)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'rates' => ['nullable', 'array'],
            'rates.*' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'notify_admin' => ['boolean'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'notify_customer' => ['boolean'],
            'telegram_enabled' => ['boolean'],
            'telegram_token' => ['nullable', 'string', 'max:100'],
            // Личный или групповой chat id (группы — с минусом) либо @канал.
            'telegram_chat_id' => ['nullable', 'string', 'max:64', 'regex:/^(-?\d+|@[A-Za-z0-9_]{5,})$/'],
        ];
    }

    /**
     * Ultimate на лицензии без него не сохраняется — иначе настройка обещала бы
     * то, чего сайт не делает.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('edition') === CartEdition::Ultimate->value && ! Nexor::feature(CartEdition::Ultimate->feature())) {
                    $validator->errors()->add('edition', 'Корзина Ultimate доступна с лицензии Standart.');
                }

                if ($this->boolean('telegram_enabled')) {
                    $token = (string) $this->input('telegram_token');
                    $hasToken = $token !== '' && ($token !== TelegramNotifier::MASK || TelegramNotifier::token());

                    if (! $hasToken) {
                        $validator->errors()->add('telegram_token', 'Для уведомлений в Telegram нужен токен бота.');
                    }

                    if (blank($this->input('telegram_chat_id'))) {
                        $validator->errors()->add('telegram_chat_id', 'Для уведомлений в Telegram нужен chat id.');
                    }
                }

                foreach (array_keys((array) $this->input('rates', [])) as $code) {
                    if (! Currency::tryFrom((string) $code)) {
                        $validator->errors()->add('rates', 'Неизвестная валюта: '.$code.'.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'edition' => 'уровень корзины',
            'display' => 'вид корзины',
            'currency' => 'валюта',
            'rates.*' => 'курс',
            'admin_email' => 'e-mail администратора',
            'telegram_token' => 'токен бота',
            'telegram_chat_id' => 'chat id',
        ];
    }
}
