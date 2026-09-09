<?php

namespace Nexor\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use Nexor\Cms\Models\Menu;

/**
 * Пустые меню шапки и подвала.
 *
 * Пункты не создаём: что в них должно быть, знает только сайт. Но сами меню
 * нужны сразу — макет зовёт их по коду, а без записи в базе он молча ничего не
 * покажет, и это выглядит как поломка.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            ['code' => 'main', 'name' => 'Главное меню', 'description' => 'Шапка сайта.', 'sort' => 100],
            ['code' => 'footer', 'name' => 'Меню подвала', 'description' => 'Нижняя часть страницы.', 'sort' => 200],
        ];

        foreach ($menus as $menu) {
            Menu::query()->firstOrCreate(['code' => $menu['code']], $menu);
        }
    }
}
