<?php

namespace Nexor\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nexor\Shop\Enums\DeliveryProvider;
use Nexor\Shop\Support\Delivery\Deliveries;

class DeliveryMethodRequest extends FormRequest
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
        $cdek = $this->input('provider') === DeliveryProvider::Cdek->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'provider' => ['nullable', Rule::enum(DeliveryProvider::class)],
            // Своя цена — только у способа без службы: у СДЭК её считает сам СДЭК.
            'price' => [$cdek ? 'nullable' : 'required', 'numeric', 'min:0', 'max:999999999'],
            'free_from' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],

            'settings' => ['array'],
            'settings.account' => [$cdek ? 'required' : 'nullable', 'string', 'max:190'],
            // Пароль может прийти маской — значит, остаётся сохранённый.
            'settings.secret' => ['nullable', 'string', 'max:255'],
            'settings.test' => ['boolean'],

            'settings.sender.name' => ['nullable', 'string', 'max:190'],
            'settings.sender.phone' => ['nullable', 'string', 'max:32'],
            'settings.article' => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9_]+$/'],

            'settings.from_city' => ['nullable', 'string', 'max:190'],
            'settings.from_code' => [$cdek ? 'required' : 'nullable', 'integer', 'min:1'],
            'settings.from_address' => ['nullable', 'string', 'max:255'],
            'settings.shipment_point' => ['nullable', 'string', 'max:64'],

            'settings.tariffs' => [$cdek ? 'required' : 'nullable', 'array', 'max:20'],
            'settings.tariffs.*.code' => ['required', 'integer', 'min:1', 'max:9999'],
            'settings.tariffs.*.name' => ['nullable', 'string', 'max:190'],
            'settings.tariffs.*.to_door' => ['boolean'],

            'settings.address.own' => ['boolean'],
            'settings.address.field' => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9_]+$/'],

            'settings.weight.property' => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9_]+$/'],
            'settings.weight.unit' => ['nullable', 'in:kg,g'],
            'settings.weight.default' => ['nullable', 'numeric', 'min:0', 'max:100000'],

            'settings.dimensions' => ['array'],
            'settings.dimensions.*.property' => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9_]+$/'],
            'settings.dimensions.*.default' => ['nullable', 'numeric', 'min:0', 'max:10000'],

            'settings.price.markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'settings.price.markup_fixed' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'settings.price.round' => ['nullable', Rule::in(Deliveries::ROUNDING)],
            'settings.price.show_period' => ['boolean'],
            'settings.price.on_error' => ['nullable', Rule::in(Deliveries::ON_ERROR)],

            'settings.map.enabled' => ['boolean'],
            'settings.map.yandex_key' => ['nullable', 'string', 'max:190'],
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
            'provider' => 'расчёт стоимости',
            'price' => 'стоимость',
            'free_from' => 'бесплатно от',
            'sort' => 'сортировка',
            'settings.account' => 'аккаунт СДЭК',
            'settings.secret' => 'секретный пароль',
            'settings.from_code' => 'город отправления',
            'settings.from_address' => 'адрес отправления',
            'settings.shipment_point' => 'пункт отправления',
            'settings.tariffs' => 'тарифы',
            'settings.tariffs.*.code' => 'код тарифа',
            'settings.tariffs.*.name' => 'название тарифа',
            'settings.sender.name' => 'имя отправителя',
            'settings.sender.phone' => 'телефон отправителя',
            'settings.article' => 'свойство с артикулом',
            'settings.address.field' => 'код поля адреса',
            'settings.weight.property' => 'свойство веса',
            'settings.weight.default' => 'вес по умолчанию',
            'settings.map.yandex_key' => 'ключ Яндекс.Карт',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.from_code.required' => 'Выберите город отправления на вкладке «Отправитель».',
            'settings.tariffs.required' => 'Добавьте хотя бы один тариф на вкладке «Тарифы».',
            'settings.account.required' => 'Введите аккаунт СДЭК на вкладке «Доступ».',
            'settings.address.field.regex' => 'Код поля заказа — латиница, цифры и подчёркивание.',
            'settings.weight.property.regex' => 'Код свойства — латиница, цифры и подчёркивание.',
            'settings.dimensions.*.property.regex' => 'Код свойства — латиница, цифры и подчёркивание.',
        ];
    }
}
