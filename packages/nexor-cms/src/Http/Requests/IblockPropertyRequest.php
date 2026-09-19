<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nexor\Cms\Enums\PropertyType;

class IblockPropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('iblocks.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $iblock = $this->route('iblock');
        $property = $this->route('property');

        return [
            'code' => [
                'required', 'string', 'max:190', 'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('iblock_properties', 'code')
                    ->where('iblock_id', $iblock->id)
                    ->ignore($property?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'hint' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PropertyType::class)],
            'is_multiple' => ['boolean'],
            'is_required' => ['boolean'],
            'is_filterable' => ['boolean'],
            'is_searchable' => ['boolean'],
            'is_shown_in_list' => ['boolean'],
            'with_description' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'default_value' => ['nullable', 'string', 'max:2000'],

            'settings' => ['array'],
            'settings.placeholder' => ['nullable', 'string', 'max:255'],
            'settings.max_length' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'settings.pattern' => ['nullable', 'string', 'max:255'],
            'settings.rows' => ['nullable', 'integer', 'min:1', 'max:50'],
            'settings.min' => ['nullable', 'numeric'],
            'settings.max' => ['nullable', 'numeric'],
            'settings.step' => ['nullable', 'numeric', 'min:0'],
            'settings.suffix' => ['nullable', 'string', 'max:50'],
            'settings.accept' => ['nullable', 'string', 'max:255'],
            'settings.max_size' => ['nullable', 'integer', 'min:1', 'max:102400'],
            'settings.max_width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'settings.max_height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'settings.link_iblock_id' => ['nullable', 'integer', Rule::exists('iblocks', 'id')],

            'enums' => ['array'],
            'enums.*.id' => ['nullable', 'integer'],
            'enums.*.value' => ['nullable', 'string', 'max:255'],
            'enums.*.code' => ['nullable', 'string', 'max:190'],
            'enums.*.sort' => ['nullable', 'integer', 'min:0'],
            'enums.*.is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Код свойства может содержать только латиницу, цифры и подчёркивание.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'код свойства',
            'name' => 'название',
            'type' => 'тип',
            'sort' => 'сортировка',
            'default_value' => 'значение по умолчанию',
            'settings.link_iblock_id' => 'связанный инфоблок',
        ];
    }
}
