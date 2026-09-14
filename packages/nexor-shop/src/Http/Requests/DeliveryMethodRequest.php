<?php

namespace Nexor\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'free_from' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
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
            'price' => 'стоимость',
            'free_from' => 'бесплатно от',
            'sort' => 'сортировка',
        ];
    }
}
