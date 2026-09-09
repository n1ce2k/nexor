<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nexor\Cms\Enums\MenuItemType;
use Nexor\Cms\Enums\MenuVisibility;

/**
 * Пункт меню.
 *
 * Обязательность полей зависит от типа: у ссылки нужен адрес, у страницы —
 * выбранный элемент, у динамического пункта — инфоблок. Проверять всё скопом
 * нельзя, иначе форма требует лишнего.
 */
class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Права проверены middleware маршрута.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $menu = $this->route('menu');
        $item = $this->route('item');
        $type = MenuItemType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(MenuItemType::class)],

            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('menu_items', 'id')->where('menu_id', $menu?->id),
                Rule::notIn(array_filter([$item?->id])),
            ],

            // У страницы и раздела имя берётся из сущности, у динамического пункта
            // своего имени нет вовсе — он раскрывается в разделы.
            'title' => [
                in_array($type, [MenuItemType::Link, MenuItemType::Heading], true) ? 'required' : 'nullable',
                'string', 'max:255',
            ],

            'url' => [$type === MenuItemType::Link ? 'required' : 'nullable', 'string', 'max:1000'],

            'iblock_id' => [
                in_array($type, [MenuItemType::Page, MenuItemType::Section, MenuItemType::Sections], true)
                    ? 'required'
                    : 'nullable',
                'integer', Rule::exists('iblocks', 'id'),
            ],

            'element_id' => [
                $type === MenuItemType::Page ? 'required' : 'nullable',
                'integer', Rule::exists('iblock_elements', 'id')->where('iblock_id', $this->input('iblock_id')),
            ],

            'section_id' => [
                $type === MenuItemType::Section ? 'required' : 'nullable',
                'integer', Rule::exists('iblock_sections', 'id')->where('iblock_id', $this->input('iblock_id')),
            ],

            'max_depth' => ['nullable', 'integer', 'min:1', 'max:5'],
            'with_elements' => ['boolean'],
            'target' => ['nullable', Rule::in(['_blank'])],
            'css_class' => ['nullable', 'string', 'max:190'],
            'icon' => ['nullable', 'string', 'max:50'],
            'visibility' => ['nullable', Rule::enum(MenuVisibility::class)],
            'highlight_children' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    /**
     * Заголовок и разделитель никуда не ведут, но заголовку нужна подпись.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('type') === MenuItemType::Divider->value) {
            $this->merge(['title' => $this->input('title') ?: '—']);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'Пункт не может быть вложен сам в себя.',
            'element_id.exists' => 'Выбранная страница не принадлежит этому инфоблоку.',
            'section_id.exists' => 'Выбранный раздел не принадлежит этому инфоблоку.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'тип пункта',
            'title' => 'название',
            'url' => 'адрес',
            'iblock_id' => 'инфоблок',
            'element_id' => 'страница',
            'section_id' => 'раздел',
            'max_depth' => 'глубина',
            'parent_id' => 'родительский пункт',
            'sort' => 'сортировка',
        ];
    }
}
