<?php

namespace Nexor\Cms\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nexor\Cms\Enums\MenuItemType;
use Nexor\Cms\Enums\MenuVisibility;
use Nexor\Cms\Support\MenuNesting;

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
                $this->placement(...),
            ],

            // У страницы и раздела имя берётся из сущности, у динамического пункта
            // своего имени нет вовсе — он раскрывается в разделы.
            'title' => [
                in_array($type, [MenuItemType::Link, MenuItemType::Heading, MenuItemType::Submenu], true) ? 'required' : 'nullable',
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
            'with_title' => ['boolean'],
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
     * Можно ли положить пункт туда, куда его кладут.
     *
     * Проверяется, только когда родитель меняется: пункт, вложенный в другой
     * до появления «Подменю», остаётся на месте и правится как обычно.
     *
     * @param  Closure(string): void  $fail
     */
    protected function placement(string $attribute, mixed $value, Closure $fail): void
    {
        $menu = $this->route('menu');
        $item = $this->route('item');
        $type = MenuItemType::tryFrom((string) $this->input('type'));

        if ($menu === null || $type === null || $value === null) {
            return;
        }

        if ($item !== null && (int) $item->parent_id === (int) $value) {
            return;
        }

        $id = $item?->id ?? 'new';
        $problem = MenuNesting::of($menu)
            ->with($id, (int) $value, $type, (int) ($this->input('max_depth') ?: 1))
            ->problem($id);

        if ($problem !== null) {
            $fail($problem);
        }
    }

    /**
     * Заголовок и разделитель никуда не ведут, но заголовку нужна подпись.
     *
     * Подменю по умолчанию — один уровень ссылок, выпадающих из своего пункта.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('type') === MenuItemType::Divider->value) {
            $this->merge(['title' => $this->input('title') ?: '—']);
        }

        if ($this->input('type') === MenuItemType::Submenu->value) {
            $this->merge([
                'max_depth' => $this->input('max_depth') ?: 1,
                'with_title' => $this->has('with_title') ? $this->boolean('with_title') : true,
            ]);
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
