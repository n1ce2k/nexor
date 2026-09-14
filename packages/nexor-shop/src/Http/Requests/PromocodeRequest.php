<?php

namespace Nexor\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nexor\Shop\Enums\PromocodeScope;
use Nexor\Shop\Enums\PromocodeType;
use Nexor\Shop\Models\Promocode;

class PromocodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Маршрут уже проверил право на промокоды.
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => Promocode::normalize((string) $this->input('code'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:60', 'regex:/^[A-Z0-9_-]+$/u',
                Rule::unique('shop_promocodes', 'code')->ignore($this->route('promocode')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PromocodeType::class)],
            'value' => [
                'required', 'numeric', 'gt:0',
                $this->input('type') === PromocodeType::Percent->value ? 'max:100' : 'max:999999999',
            ],
            'min_sum' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:4000000000'],
            'scope' => ['required', Rule::enum(PromocodeScope::class)],
            'section_ids' => ['nullable', 'array', Rule::requiredIf($this->input('scope') === PromocodeScope::Sections->value)],
            'section_ids.*' => ['integer', 'exists:iblock_sections,id'],
            'element_ids' => ['nullable', 'array', Rule::requiredIf($this->input('scope') === PromocodeScope::Elements->value)],
            'element_ids.*' => ['integer', 'exists:iblock_elements,id'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Данные для модели: цели — только те, что относятся к выбранному охвату.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        $data['section_ids'] = $data['scope'] === PromocodeScope::Sections->value ? array_values($data['section_ids'] ?? []) : null;
        $data['element_ids'] = $data['scope'] === PromocodeScope::Elements->value ? array_values($data['element_ids'] ?? []) : null;

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'код',
            'description' => 'описание',
            'type' => 'тип скидки',
            'value' => 'размер скидки',
            'min_sum' => 'минимальная сумма',
            'starts_at' => 'начало действия',
            'ends_at' => 'окончание действия',
            'usage_limit' => 'лимит использований',
            'scope' => 'охват',
            'section_ids' => 'разделы',
            'element_ids' => 'товары',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Промокод — латиница, цифры, дефис и подчёркивание.',
        ];
    }
}
