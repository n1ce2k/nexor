<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nexor\Cms\Enums\ElementUrl;
use Nexor\Cms\Enums\PaginationTemplate;
use Nexor\Cms\Support\Modules\ModuleFields;

class IblockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->route('iblock') ? 'iblocks.update' : 'iblocks.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ModuleFields::iblockSettingRules() + [
            'iblock_type_id' => ['required', 'integer', Rule::exists('iblock_types', 'id')],
            'code' => [
                'required', 'string', 'max:190', 'regex:/^[a-z0-9_-]+$/',
                // Код из одних цифр неотличим от id: в компонентах iblock="5" — это id.
                'not_regex:/^[0-9]+$/',
                Rule::unique('iblocks', 'code')->ignore($this->route('iblock')?->id)->withoutTrashed(),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'picture' => ['nullable', 'image', 'max:4096'],
            'picture_remove' => ['boolean'],
            'list_url' => ['nullable', 'string', 'max:255'],
            'section_url' => ['nullable', 'string', 'max:255'],
            'detail_url' => ['nullable', 'string', 'max:255'],
            'element_url' => ['nullable', Rule::enum(ElementUrl::class)],
            'has_sections' => ['boolean'],
            'has_page' => ['boolean'],
            'is_catalog' => ['boolean'],
            'element_name' => ['nullable', 'string', 'max:100'],
            'pagination_template' => ['nullable', Rule::enum(PaginationTemplate::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'has_load_more' => ['boolean'],
            'load_more_size' => ['nullable', 'integer', 'min:1', 'max:500'],
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
            'code.not_regex' => 'Символьный код не может состоять из одних цифр — так в компонентах пишется id инфоблока.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'iblock_type_id' => 'тип инфоблока',
            'code' => 'символьный код',
            'name' => 'название',
            'element_name' => 'название сущности',
            'description' => 'описание',
            'picture' => 'картинка',
            'has_page' => 'создание страницы',
            'pagination_template' => 'шаблон пагинации',
            'per_page' => 'элементов на странице',
            'load_more_size' => 'сколько отображать',
            'list_url' => 'URL списка',
            'section_url' => 'URL раздела',
            'detail_url' => 'URL детальной страницы',
            'element_url' => 'канонический адрес элемента',
            'sort' => 'сортировка',
        ];
    }
}
