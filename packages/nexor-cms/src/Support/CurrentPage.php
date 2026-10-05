<?php

namespace Nexor\Cms\Support;

use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;

/**
 * Что за страница открыта сейчас: инфоблок, раздел, элемент.
 *
 * Контроллер страниц это и так знает, а макету сайта узнать неоткуда. Поэтому
 * он записывает сюда, и `<x-nexor::breadcrumbs />` в макете — один на весь
 * сайт — строит путь сам, без параметров на каждой странице.
 *
 * Живёт в запросе, а не в контейнере: между запросами одного процесса (тесты,
 * Octane) страница обязана забываться.
 */
class CurrentPage
{
    protected ?Iblock $iblock = null;

    protected ?IblockSection $section = null;

    protected ?IblockElement $element = null;

    /** @var array<int, array{name: string, url: string|null}> */
    protected array $crumbs = [];

    protected bool $breadcrumbsShown = false;

    public static function get(): self
    {
        return app(self::class);
    }

    /**
     * Открыта страница инфоблока: его список, раздел или элемент.
     */
    public function open(Iblock $iblock, ?IblockSection $section = null, ?IblockElement $element = null): void
    {
        $this->iblock = $iblock;
        $this->section = $section;
        $this->element = $element;
    }

    public function iblock(): ?Iblock
    {
        return $this->iblock;
    }

    public function section(): ?IblockSection
    {
        return $this->section;
    }

    public function element(): ?IblockElement
    {
        return $this->element;
    }

    /**
     * Своя крошка — для страниц не из инфоблоков: корзина, поиск, свои маршруты.
     *
     * В шаблоне: `@breadcrumb('Корзина')` или `@breadcrumb('Акции', '/sale')`.
     */
    public function crumb(string $name, ?string $url = null): void
    {
        $this->crumbs[] = ['name' => $name, 'url' => $url];
    }

    /**
     * @return array<int, array{name: string, url: string|null}>
     */
    public function crumbs(): array
    {
        return $this->crumbs;
    }

    /**
     * Крошки на этой странице уже выведены — второй раз макет их не повторит.
     */
    public function markBreadcrumbsShown(): void
    {
        $this->breadcrumbsShown = true;
    }

    public function breadcrumbsShown(): bool
    {
        return $this->breadcrumbsShown;
    }
}
