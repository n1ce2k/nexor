<?php

namespace Nexor\PageBuilder\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Шаблоны карточек компонентов для блока «Каталог».
 *
 * В блоке пишется имя шаблона без префикса — `catalog.card.mini`, а на сайте
 * подключается `nexor::components.catalog.card.mini`. Файл берётся по обычным
 * правилам компонентов: копия сайта в resources/views/vendor/nexor/components
 * важнее шаблона пакета. Свой шаблон: `php artisan nexor:component catalog.card mini`.
 */
class CardTemplates
{
    public const PREFIX = 'nexor::components.';

    public const DEFAULT = 'catalog.card.default';

    /** Имя шаблона: латиница, цифры, точки, дефис и подчёркивание. */
    public const PATTERN = '/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)+$/';

    /**
     * Приводит введённое к имени без префикса: вставленное целиком
     * `nexor::components.catalog.card.mini` тоже подходит.
     */
    public static function normalize(?string $name): string
    {
        $name = trim((string) $name);

        return str_starts_with($name, self::PREFIX) ? substr($name, strlen(self::PREFIX)) : $name;
    }

    public static function exists(string $name): bool
    {
        return preg_match(self::PATTERN, $name) === 1 && View::exists(self::PREFIX.$name);
    }

    /**
     * Вьюха для сайта. Шаблон удалили или имя пустое — стандартная карточка.
     */
    public static function view(?string $name): string
    {
        $name = self::normalize($name);

        return self::PREFIX.($name !== '' && self::exists($name) ? $name : self::DEFAULT);
    }

    /**
     * Уже созданные карточки — подсказка в редакторе.
     *
     * Ищутся шаблоны в папках `components/<компонент>/card/` пакета и сайта.
     *
     * @return array<int, string>
     */
    public static function available(): array
    {
        $names = [];

        foreach (View::getFinder()->getHints()['nexor'] ?? [] as $root) {
            foreach (File::glob($root.'/components/*/card/*.blade.php') as $file) {
                $component = basename(dirname($file, 2));
                $names[] = $component.'.card.'.basename($file, '.blade.php');
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
