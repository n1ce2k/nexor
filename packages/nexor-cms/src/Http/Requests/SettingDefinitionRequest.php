<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nexor\Cms\Models\Setting;

class SettingDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $setting = $this->route('setting');

        return [
            'key' => [
                $setting?->is_system ? 'nullable' : 'required',
                'string', 'max:190', 'regex:/^[a-z0-9_]+\.[a-z0-9_.]+$/',
                Rule::unique('settings', 'key')->ignore($setting?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'hint' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Setting::types()))],
            'group' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],

            'options' => ['array'],
            'options.*.value' => ['required_with:options', 'string', 'max:190'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => 'Ключ пишется как группа.имя — латиница в нижнем регистре, цифры и подчёркивание. Например: contacts.telegram',
            'group.regex' => 'Код группы может содержать только латиницу в нижнем регистре, цифры и подчёркивание.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'key' => 'ключ',
            'name' => 'название',
            'type' => 'тип',
            'group' => 'группа',
            'sort' => 'сортировка',
            'options' => 'варианты списка',
        ];
    }
}
