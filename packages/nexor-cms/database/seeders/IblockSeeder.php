<?php

namespace Nexor\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockType;
use Nexor\Cms\Support\Permissions;

class IblockSeeder extends Seeder
{
    public function run(): void
    {
        $content = IblockType::query()->updateOrCreate(
            ['code' => 'content'],
            [
                'name' => 'Контент',
                'sections_name' => 'Разделы',
                'elements_name' => 'Материалы',
                'description' => 'Текстовые материалы сайта: страницы, новости, статьи.',
                'has_sections' => true,
                'sort' => 100,
            ],
        );

        IblockType::query()->updateOrCreate(
            ['code' => 'catalog'],
            [
                'name' => 'Каталог',
                'sections_name' => 'Разделы каталога',
                'elements_name' => 'Товары',
                'description' => 'Каталоги продукции и палитры цветов.',
                'has_sections' => true,
                'sort' => 200,
            ],
        );

        IblockType::query()->updateOrCreate(
            ['code' => 'service'],
            [
                'name' => 'Служебные',
                'sections_name' => 'Группы',
                'elements_name' => 'Записи',
                'description' => 'Баннеры, меню, формы и прочие служебные наборы данных.',
                'has_sections' => false,
                'sort' => 300,
            ],
        );

        $pages = Iblock::query()->updateOrCreate(
            ['code' => 'pages'],
            [
                'iblock_type_id' => $content->id,
                'name' => 'Страницы',
                'description' => 'Статические страницы сайта.',
                'list_url' => '/pages',
                'detail_url' => '/{code}',
                'has_sections' => false,
                'sort' => 100,
            ],
        );

        $this->seedPageProperties($pages);

        Permissions::syncAll();
    }

    protected function seedPageProperties(Iblock $iblock): void
    {
        $properties = [
            [
                'code' => 'subtitle',
                'name' => 'Подзаголовок',
                'type' => PropertyType::String,
                'sort' => 100,
                'is_shown_in_list' => true,
            ],
            [
                'code' => 'show_in_menu',
                'name' => 'Показывать в меню',
                'type' => PropertyType::Boolean,
                'sort' => 200,
                'is_filterable' => true,
                'is_shown_in_list' => true,
                'default_value' => '0',
            ],
            [
                'code' => 'menu_sort',
                'name' => 'Позиция в меню',
                'type' => PropertyType::Integer,
                'type_settings' => ['min' => 0, 'step' => 10],
                'sort' => 300,
                'default_value' => '500',
            ],
            [
                'code' => 'template',
                'name' => 'Шаблон страницы',
                'type' => PropertyType::Select,
                'sort' => 400,
                'is_filterable' => true,
                'enums' => ['Обычная', 'Широкая', 'Лендинг'],
            ],
        ];

        foreach ($properties as $definition) {
            $enums = $definition['enums'] ?? [];
            $settings = $definition['type_settings'] ?? null;
            unset($definition['enums'], $definition['type_settings']);

            /** @var IblockProperty $property */
            $property = IblockProperty::query()->updateOrCreate(
                ['iblock_id' => $iblock->id, 'code' => $definition['code']],
                $definition + ['iblock_id' => $iblock->id, 'settings' => $settings],
            );

            foreach ($enums as $index => $value) {
                $property->enums()->updateOrCreate(
                    ['value' => $value],
                    ['sort' => ($index + 1) * 100, 'is_default' => $index === 0],
                );
            }
        }
    }
}
