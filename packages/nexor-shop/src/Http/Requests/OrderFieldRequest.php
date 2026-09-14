<?php

namespace Nexor\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nexor\Shop\Enums\OrderFieldType;

class OrderFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Маршрут уже проверил право shop.cart.update.
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('shop_order_fields', 'code')->ignore($this->route('field')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(OrderFieldType::class)],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'код', 'name' => 'название', 'type' => 'тип'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Код поля — латиница в верхнем регистре, цифры и подчёркивание, начиная с буквы.',
        ];
    }
}
