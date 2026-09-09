<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Support\Site;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $iblock = Iblock::query()->where('code', Site::PAGES)->first();

        if (! $iblock) {
            return;
        }

        $pages = [
            [
                'code' => 'about',
                'name' => 'О компании',
                'subtitle' => 'Кто мы и чем занимаемся',
                'preview_text' => 'Коротко о компании, направлениях работы и подходе к делу.',
                'detail_text' => '<h2>О компании</h2><p>Замените этот текст в панели управления: раздел «Контент» → «Страницы».</p><p>Страница создана автоматически при установке проекта, чтобы было видно, как работает связка «инфоблок → элемент → публичная страница».</p>',
            ],
            [
                'code' => 'services',
                'name' => 'Услуги',
                'subtitle' => 'Что мы предлагаем',
                'preview_text' => 'Перечень услуг с описанием и условиями.',
                'detail_text' => '<h2>Услуги</h2><ul><li>Первая услуга</li><li>Вторая услуга</li><li>Третья услуга</li></ul><p>Список редактируется в админке.</p>',
            ],
            [
                'code' => 'delivery',
                'name' => 'Доставка и оплата',
                'subtitle' => 'Условия работы',
                'preview_text' => 'Способы оплаты, сроки и регионы доставки.',
                'detail_text' => '<h2>Доставка</h2><p>Опишите здесь условия доставки.</p><h2>Оплата</h2><p>Опишите здесь способы оплаты.</p>',
            ],
            [
                'code' => 'contacts',
                'name' => 'Контакты',
                'subtitle' => 'Как с нами связаться',
                'preview_text' => 'Телефон, адрес и режим работы.',
                'detail_text' => '<h2>Контакты</h2><p>Телефон, e-mail и адрес заполняются в разделе «Настройки» и подставляются в подвал сайта автоматически.</p>',
            ],
        ];

        $properties = $iblock->properties()->get()->keyBy('code');

        foreach ($pages as $index => $definition) {
            $element = IblockElement::query()->updateOrCreate(
                ['iblock_id' => $iblock->id, 'code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'preview_text' => $definition['preview_text'],
                    'preview_text_type' => 'text',
                    'detail_text' => $definition['detail_text'],
                    'detail_text_type' => 'html',
                    'is_active' => true,
                    'sort' => ($index + 1) * 100,
                ],
            );

            $this->setValue($element, $properties->get('subtitle'), 'value_string', $definition['subtitle']);
        }
    }

    protected function setValue(IblockElement $element, ?IblockProperty $property, string $column, mixed $value): void
    {
        if (! $property) {
            return;
        }

        $element->values()->updateOrCreate(
            ['property_id' => $property->id],
            [$column => $value, 'sort' => 100],
        );
    }
}
