<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IblockSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware `iblock:` already checked the infoblock permission.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $iblock = $this->route('iblock');
        $section = $this->route('section');

        return [
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('iblock_sections', 'id')->where('iblock_id', $iblock->id),
                Rule::notIn(array_filter([$section?->id])),
            ],
            'code' => [
                'nullable', 'string', 'max:190', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('iblock_sections', 'code')->where('iblock_id', $iblock->id)->ignore($section?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'picture' => ['nullable', 'image', 'max:4096'],
            'picture_remove' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Символьный код может содержать только латиницу в нижнем регистре, цифры, дефис и подчёркивание.',
            'parent_id.not_in' => 'Раздел не может быть родителем самому себе.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => 'родительский раздел',
            'code' => 'символьный код',
            'name' => 'название',
            'sort' => 'сортировка',
        ];
    }
}
