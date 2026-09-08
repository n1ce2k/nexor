<?php

namespace Nexor\Cms\View\Components;

use Illuminate\View\Component as BaseComponent;
use Illuminate\View\View;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Services\InfoBlockService;
use RuntimeException;

/**
 * Общий предок компонентов публичной части.
 *
 * У каждого компонента две независимые ручки, как в Битриксе: сам компонент —
 * это логика выборки, а проп `template` выбирает вёрстку. Шаблоны лежат в
 * пакете, но сайт может положить свой рядом:
 *
 *     php artisan nexor:component catalog.section
 *
 * После этого файл в `resources/views/vendor/nexor/components/...` побеждает
 * пакетный, а обновление CMS его не трогает.
 */
abstract class Component extends BaseComponent
{
    /** Папка шаблонов этого компонента, например `catalog.section`. */
    abstract protected function component(): string;

    protected function iblocks(): InfoBlockService
    {
        return app(InfoBlockService::class);
    }

    /**
     * Опечатка в коде инфоблока должна быть видна сразу, а не в виде пустого блока.
     */
    protected function requireIblock(string $code): Iblock
    {
        return $this->iblocks()->getInfoBlockByCode($code)
            ?? throw new RuntimeException("Инфоблок «{$code}» не найден или отключён.");
    }

    /**
     * Вьюха выбранного шаблона.
     *
     * @param  array<string, mixed>  $data
     */
    protected function template(string $name, array $data): View
    {
        $view = 'nexor::components.'.$this->component().'.'.$name;

        if (! view()->exists($view)) {
            throw new RuntimeException(
                "Шаблон «{$name}» компонента «{$this->component()}» не найден. ".
                'Ожидался файл resources/views/vendor/nexor/components/'.
                str_replace('.', '/', $this->component())."/{$name}.blade.php."
            );
        }

        return view($view, $data);
    }
}
