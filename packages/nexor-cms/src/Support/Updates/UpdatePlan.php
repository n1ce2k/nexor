<?php

namespace Nexor\Cms\Support\Updates;

/**
 * Из чего складывается задача: какие команды и в каком порядке.
 *
 * Имена пакетов сюда приходят только из PackageCatalog, поэтому в composer не
 * может попасть ничего, чего нет в списке.
 */
class UpdatePlan
{
    /**
     * Обновление установленных пакетов NEXOR.
     *
     * @param  array<int, string>  $packages  Имена пакетов composer
     * @return array{title: string, steps: array<int, array{type: string, arguments: array<int, string>, title: string}>}
     */
    public static function update(array $packages): array
    {
        $arguments = ['update', ...$packages, '--with-dependencies', '--no-interaction', '--prefer-dist', '--no-ansi'];

        return [
            'title' => 'Обновление: '.implode(', ', $packages),
            'steps' => [
                self::step('composer', $arguments),
                self::step('artisan', ['migrate', '--force', '--no-ansi']),
                self::step('artisan', ['nexor:permissions']),
                self::step('artisan', ['config:clear']),
                self::step('artisan', ['view:clear']),
            ],
        ];
    }

    /**
     * Установка модуля: сам пакет и его установщик.
     *
     * @param  array{package: string, name: string, installer: string|null}  $entry
     * @return array{title: string, steps: array<int, array{type: string, arguments: array<int, string>, title: string}>}
     */
    public static function install(array $entry): array
    {
        $steps = [
            self::step('composer', ['require', $entry['package'], '--no-interaction', '--prefer-dist', '--no-ansi']),
        ];

        if ($entry['installer'] !== null) {
            $steps[] = self::step('artisan', [$entry['installer'], '--no-interaction']);
        }

        $steps[] = self::step('artisan', ['nexor:permissions']);
        $steps[] = self::step('artisan', ['config:clear']);

        return [
            'title' => 'Установка модуля «'.$entry['name'].'»',
            'steps' => $steps,
        ];
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array{type: string, arguments: array<int, string>, title: string}
     */
    protected static function step(string $type, array $arguments): array
    {
        return [
            'type' => $type,
            'arguments' => $arguments,
            'title' => ($type === 'artisan' ? 'php artisan ' : 'composer ').implode(' ', $arguments),
        ];
    }
}
