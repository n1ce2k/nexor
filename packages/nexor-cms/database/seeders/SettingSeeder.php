<?php

namespace Nexor\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\MailConfig;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $setting = Setting::query()->firstOrNew(['key' => $definition['key']]);

            // Re-seeding refreshes labels and grouping but never overwrites saved values.
            $setting->fill($setting->exists ? array_diff_key($definition, ['value' => null]) : $definition);
            $setting->is_system = true;
            $setting->save();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function definitions(): array
    {
        $mail = array_map(
            fn (array $definition) => $definition + ['group' => 'mail'],
            MailConfig::definitions(),
        );

        return [
            ['key' => 'site.name', 'value' => 'NEXOR', 'type' => 'string', 'group' => 'general', 'name' => 'Название сайта', 'sort' => 100],
            ['key' => 'site.tagline', 'value' => 'Панель управления сайтом', 'type' => 'string', 'group' => 'general', 'name' => 'Слоган', 'sort' => 110],
            ['key' => 'site.logo', 'value' => null, 'type' => 'image', 'group' => 'general', 'name' => 'Логотип', 'sort' => 120],
            ['key' => 'site.favicon', 'value' => null, 'type' => 'image', 'group' => 'general', 'name' => 'Favicon', 'sort' => 130],
            ['key' => 'site.maintenance', 'value' => '0', 'type' => 'boolean', 'group' => 'general', 'name' => 'Режим обслуживания', 'hint' => 'Публичная часть закрывается заглушкой для всех, кроме авторизованных.', 'sort' => 140],
            ['key' => 'site.maintenance_message', 'value' => '', 'type' => 'text', 'group' => 'general', 'name' => 'Текст заглушки', 'hint' => 'Показывается на странице обслуживания. Пусто — текст по умолчанию.', 'sort' => 150],

            ['key' => 'contacts.phone', 'value' => '', 'type' => 'string', 'group' => 'contacts', 'name' => 'Телефон', 'sort' => 200],
            ['key' => 'contacts.email', 'value' => '', 'type' => 'string', 'group' => 'contacts', 'name' => 'E-mail', 'sort' => 210],
            ['key' => 'contacts.address', 'value' => '', 'type' => 'text', 'group' => 'contacts', 'name' => 'Адрес', 'sort' => 220],
            ['key' => 'contacts.work_hours', 'value' => '', 'type' => 'string', 'group' => 'contacts', 'name' => 'Режим работы', 'sort' => 230],

            ['key' => 'seo.meta_title', 'value' => '', 'type' => 'string', 'group' => 'seo', 'name' => 'Заголовок по умолчанию', 'sort' => 300],
            ['key' => 'seo.meta_description', 'value' => '', 'type' => 'text', 'group' => 'seo', 'name' => 'Описание по умолчанию', 'sort' => 310],
            ['key' => 'seo.robots', 'value' => "User-agent: *\nAllow: /", 'type' => 'text', 'group' => 'seo', 'name' => 'robots.txt', 'sort' => 320],
            ['key' => 'seo.counters', 'value' => '', 'type' => 'text', 'group' => 'seo', 'name' => 'Коды счётчиков', 'hint' => 'HTML, вставляется перед закрывающим тегом body.', 'sort' => 330],

            ...$mail,
        ];
    }
}
