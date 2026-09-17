<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\UserField;

class UserFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('user_fields.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('user_fields', 'code')->ignore($this->route('field')?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'hint' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_map(fn (PropertyType $type) => $type->value, UserField::types()))],
            'is_required' => ['boolean'],
            'is_multiple' => ['boolean'],
            'is_shown_in_list' => ['boolean'],
            'is_filterable' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],

            'settings' => ['nullable', 'array'],
            'settings.max_length' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'settings.max_size' => ['nullable', 'integer', 'min:1', 'max:51200'],
            'settings.options' => ['nullable', 'array', 'max:200'],
            'settings.options.*.value' => ['required', 'string', 'max:255', 'distinct'],
            'settings.options.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'символьный код',
            'name' => 'название',
            'hint' => 'подсказка',
            'type' => 'тип',
            'settings.max_length' => 'длина значения',
            'settings.max_size' => 'размер файла',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Код поля — латиница, цифры и подчёркивание, начинается с буквы.',
            'settings.options.*.value.distinct' => 'Значения вариантов не должны повторяться.',
        ];
    }
}
