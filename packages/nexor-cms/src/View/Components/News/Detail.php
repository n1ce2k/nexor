<?php

namespace Nexor\Cms\View\Components\News;

use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\View\Components\Catalog\Element;

/**
 * Детальная новость — аналог `news.detail`.
 *
 * ```blade
 * <x-nexor::news.detail :element="$element" />
 * <x-nexor::news.detail iblock="news" code="otkrytie-magazina" />
 * <x-nexor::news.detail iblock="news" :id="5" />
 * ```
 *
 * От `catalog.element` отличается вёрсткой: дата публикации в шапке, таблица
 * свойств по умолчанию скрыта — у новости их обычно нет.
 */
class Detail extends Element
{
    public function __construct(
        ?IblockElement $element = null,
        ?string $iblock = null,
        ?string $code = null,
        string $template = 'default',
        bool $properties = false,
        int|string|null $id = null,
    ) {
        parent::__construct(
            element: $element,
            iblock: $iblock,
            code: $code,
            template: $template,
            properties: $properties,
            id: $id,
        );
    }

    protected function component(): string
    {
        return 'news.detail';
    }
}
