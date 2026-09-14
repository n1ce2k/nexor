<?php

namespace Nexor\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'название', 'description' => 'описание', 'sort' => 'сортировка'];
    }
}
