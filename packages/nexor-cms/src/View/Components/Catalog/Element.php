<?php

namespace Nexor\Cms\View\Components\Catalog;

use Illuminate\View\View;
use Nexor\Cms\Exceptions\IblockUnavailable;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\View\Components\Component;
use RuntimeException;

/**
 * Детальная карточка элемента — аналог `catalog.element` и `news.detail`.
 *
 * ```blade
 * <x-nexor::catalog.element :element="$element" :offer="$offer" />
 * <x-nexor::catalog.element iblock="katalog" code="stul-venskiy" />
 * <x-nexor::catalog.element iblock="katalog" :id="15" />
 * ```
 *
 * Элемент можно передать готовым — так его отдаёт маршрут деталки, — либо
 * назвать инфоблок и код или id, и компонент найдёт его сам. Найденный так
 * элемент должен быть опубликован; нет такого или он скрыт — компонент не
 * выводит ничего, чтобы удалённая новость не роняла, например, главную.
 */
class Element extends Component
{
    /**
     * @param  IblockElement|null  $element  Готовый элемент
     * @param  string|null  $iblock  Символьный код инфоблока, если элемент не передан
     * @param  string|null  $code  Символьный код элемента
     * @param  int|string|null  $id  Id элемента — вместо кода
     * @param  string  $template  Имя шаблона вёрстки
     * @param  bool  $properties  Выводить ли таблицу свойств
     * @param  IblockElement|null  $offer  Выбранное торговое предложение — из адреса страницы.
     *                                     У товара со списком предложений не используется.
     */
    public function __construct(
        public ?IblockElement $element = null,
        public ?string $iblock = null,
        public ?string $code = null,
        public string $template = 'default',
        public bool $properties = true,
        public ?IblockElement $offer = null,
        public int|string|null $id = null,
    ) {}

    /**
     * Элемент ищется здесь, до вёрстки: не нашёлся — компонента на странице нет.
     */
    public function shouldRender(): bool
    {
        try {
            $this->element ??= $this->find();
        } catch (IblockUnavailable $exception) {
            // Отключённый инфоблок — заглушка вместо компонента, а не ошибка страницы.
            $this->missingIblock = $exception;

            return true;
        }

        return $this->element !== null;
    }

    protected ?IblockUnavailable $missingIblock = null;

    public function render(): View
    {
        if ($this->missingIblock) {
            return $this->unavailable($this->missingIblock);
        }

        // Публичный проп перекрывает данные вьюхи, поэтому найденный элемент
        // кладётся именно в проп, а не в массив данных.
        $this->element ??= $this->find();

        $offers = $this->element->offerElements();
        $offersByProperties = ! $this->element->catalog?->listsOffers();

        // Переключатель: без предложения в адресе выбрано первое доступное, а если
        // купить нечего — первое. Списку выбранное не нужно — видны все сразу.
        $this->offer = $offersByProperties
            ? $this->offer ?? $offers->first(fn (IblockElement $offer) => $offer->catalog?->isAvailable()) ?? $offers->first()
            : null;

        return $this->template($this->template, [
            'block' => $this->element->iblock,
            'values' => $this->properties ? $this->element->propertyValues() : collect(),
            'showProperties' => $this->properties,
            'offers' => $offers,
            'offersByProperties' => $offersByProperties,
        ]);
    }

    protected function component(): string
    {
        return 'catalog.element';
    }

    /**
     * Опубликованный элемент по коду или id. Ошибка в вызове (нет инфоблока,
     * нет ни кода, ни id, неизвестный инфоблок) видна сразу, а пропавший
     * элемент — просто пустое место.
     */
    protected function find(): ?IblockElement
    {
        $hasId = $this->id !== null && $this->id !== '';

        if (! $this->iblock || (! $this->code && ! $hasId)) {
            throw new RuntimeException("Компоненту {$this->component()} нужен либо :element, либо iblock вместе с code или id.");
        }

        if ($hasId && ! ctype_digit((string) $this->id)) {
            throw new RuntimeException("Компоненту {$this->component()} передан id «{$this->id}» — нужно число.");
        }

        $this->requireIblock($this->iblock);

        $filter = ['is_active' => true] + ($hasId ? ['id' => (int) $this->id] : ['code' => $this->code]);

        return $this->iblocks()->getElements($this->iblock, $filter, limit: 1)->first();
    }
}
