<?php

namespace Nexor\Cms\View\Components\Catalog;

use Illuminate\View\View;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\View\Components\Component;
use RuntimeException;

/**
 * Детальная карточка элемента — аналог `catalog.element` и `news.detail`.
 *
 * ```blade
 * <x-nexor::catalog.element :element="$element" />
 * <x-nexor::catalog.element iblock="katalog" code="stul-venskiy" />
 * ```
 *
 * Элемент можно передать готовым — так его отдаёт маршрут деталки, — либо
 * назвать инфоблок и код, и компонент найдёт его сам.
 */
class Element extends Component
{
    /**
     * @param  IblockElement|null  $element  Готовый элемент
     * @param  string|null  $iblock  Символьный код инфоблока, если элемент не передан
     * @param  string|null  $code  Символьный код элемента
     * @param  string  $template  Имя шаблона вёрстки
     * @param  bool  $properties  Выводить ли таблицу свойств
     */
    public function __construct(
        public ?IblockElement $element = null,
        public ?string $iblock = null,
        public ?string $code = null,
        public string $template = 'default',
        public bool $properties = true,
    ) {}

    public function render(): View
    {
        // Публичный проп перекрывает данные вьюхи, поэтому найденный элемент
        // кладётся именно в проп, а не в массив данных.
        $this->element ??= $this->find();

        return $this->template($this->template, [
            'block' => $this->element->iblock,
            'values' => $this->properties ? $this->element->propertyValues() : collect(),
            'showProperties' => $this->properties,
        ]);
    }

    protected function component(): string
    {
        return 'catalog.element';
    }

    protected function find(): IblockElement
    {
        if (! $this->iblock || ! $this->code) {
            throw new RuntimeException('Компоненту catalog.element нужен либо :element, либо пара iblock и code.');
        }

        return $this->iblocks()->getElementByCode($this->iblock, $this->code)
            ?? throw new RuntimeException("Элемент «{$this->code}» инфоблока «{$this->iblock}» не найден.");
    }
}
