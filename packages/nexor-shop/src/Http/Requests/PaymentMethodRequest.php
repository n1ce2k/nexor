<?php

namespace Nexor\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nexor\Shop\Enums\PaymentProvider;

class PaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Маршрут уже проверил право shop.checkout.update.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $online = $this->input('provider') === PaymentProvider::YooKassa->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'provider' => ['required', Rule::enum(PaymentProvider::class)],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'settings' => ['array'],
            'settings.shop_id' => [$online ? 'required' : 'nullable', 'string', 'max:64'],
            // Ключ может прийти маской — значит, остаётся сохранённый.
            'settings.secret_key' => ['nullable', 'string', 'max:255'],
            'settings.description' => ['nullable', 'string', 'max:128'],
            'settings.auto_redirect' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'название',
            'description' => 'описание',
            'sort' => 'сортировка',
            'provider' => 'приём оплаты',
            'settings.shop_id' => 'идентификатор магазина',
            'settings.secret_key' => 'секретный ключ',
            'settings.description' => 'назначение платежа',
            'settings.auto_redirect' => 'переход на оплату',
        ];
    }
}
