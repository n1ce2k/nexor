<?php

namespace Nexor\Cms\View\Components;

use Illuminate\View\View;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;

/**
 * Хлебные крошки: Главная — инфоблок — разделы — элемент.
 *
 * ```blade
 * <x-nexor::breadcrumbs iblock="katalog" />
 * <x-nexor::breadcrumbs iblock="katalog" :element="$element" />
 * ```
 *
 * Раздел подставляется сам: из элемента, если он передан, иначе из `?section=`.
 */
class Breadcrumbs extends Component
{
    /**
     * @param  string  $iblock  Символьный код инфоблока
     * @param  IblockElement|null  $element  Элемент, если это детальная страница
     * @param  IblockSection|null  $section  Раздел; по умолчанию берётся из адреса
     * @param  string  $template  Имя шаблона вёрстки
     */
    public function __construct(
        public string $iblock,
        public ?IblockElement $element = null,
        public ?IblockSection $section = null,
        public string $template = 'default',
    ) {}

    public function render(): View
    {
        $section = $this->section ?? $this->fromRequest();

        return $this->template($this->template, [
            'crumbs' => $this->iblocks()->getBreadcrumbs($this->iblock, $this->element, $section),
        ]);
    }

    protected function component(): string
    {
        return 'breadcrumbs';
    }

    protected function fromRequest(): ?IblockSection
    {
        $code = request()->query('section');

        if ($this->element || ! is_string($code) || $code === '' || $code === 'none') {
            return null;
        }

        return $this->requireIblock($this->iblock)->has_sections
            ? $this->iblocks()->getSectionByCode($this->iblock, $code)
            : null;
    }
}
