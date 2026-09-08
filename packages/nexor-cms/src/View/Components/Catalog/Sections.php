<?php

namespace Nexor\Cms\View\Components\Catalog;

use Illuminate\View\View;
use Nexor\Cms\View\Components\Component;

/**
 * Меню разделов инфоблока.
 *
 * ```blade
 * <x-nexor::catalog.sections iblock="katalog" />              чипсами, верхний уровень
 * <x-nexor::catalog.sections iblock="katalog" template="tree" />  деревом целиком
 * ```
 *
 * Текущий раздел подсвечивается по `?section=` — тому же параметру, который
 * читает список.
 */
class Sections extends Component
{
    /**
     * @param  string  $iblock  Символьный код инфоблока
     * @param  string  $template  Имя шаблона вёрстки
     * @param  string|null  $url  Адрес страницы, к которой дописывается `?section=`
     */
    public function __construct(
        public string $iblock,
        public string $template = 'default',
        public ?string $url = null,
    ) {}

    public function render(): View
    {
        $block = $this->requireIblock($this->iblock);

        return $this->template($this->template, [
            'block' => $block,
            'tree' => $block->has_sections ? $this->iblocks()->getSectionsTree($this->iblock) : [],
            'current' => (string) request()->query('section', ''),
            'baseUrl' => $this->url ?? url('/'.$block->code),
        ]);
    }

    protected function component(): string
    {
        return 'catalog.sections';
    }
}
