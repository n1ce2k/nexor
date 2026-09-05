<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IblockTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission(
            $this->route('iblockType') ? 'iblock_types.update' : 'iblock_types.create',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:190', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('iblock_types', 'code')->ignore($this->route('iblockType')?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sections_name' => ['nullable', 'string', 'max:255'],
            'elements_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'has_sections' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Символьный код может содержать только латиницу в нижнем регистре, цифры, дефис и подчёркивание.',
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
            'sections_name' => 'название разделов',
            'elements_name' => 'название элементов',
            'description' => 'описание',
            'sort' => 'сортировка',
        ];
    }
}
