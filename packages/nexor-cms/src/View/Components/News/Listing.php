<?php

namespace Nexor\Cms\View\Components\News;

use Nexor\Cms\View\Components\Catalog\Section;

/**
 * Лента новостей — аналог `news.list`.
 *
 * ```blade
 * <x-nexor::news.list iblock="news" />
 * <x-nexor::news.list iblock="news" :per-page="5" template="compact" />
 * <x-nexor::news.list iblock="news" :section_id="4" />
 * ```
 *
 * Логика та же, что у `catalog.section`: под ними один InfoBlockService над
 * любым инфоблоком. Отличаются умолчаниями — новости идут от свежих к старым —
 * и вёрсткой: в карточке дата и анонс, а не картинка товара.
 *
 * Класс называется Listing, а тег `news.list`: `list` в PHP — зарезервированное
 * слово, класса `News\List` не бывает. Тег связан с классом псевдонимом.
 */
class Listing extends Section
{
    public function __construct(
        string $iblock,
        string $template = 'default',
        ?string $section = null,
        array $filter = [],
        array $order = ['created_at' => 'desc'],
        ?int $perPage = null,
        string $card = 'nexor::components.news.card.default',
        bool $recursive = true,
        bool $paginate = true,
        ?int $limit = null,
        int|string|null $sectionId = null,
    ) {
        parent::__construct(
            iblock: $iblock,
            template: $template,
            section: $section,
            filter: $filter,
            order: $order,
            perPage: $perPage,
            card: $card,
            recursive: $recursive,
            paginate: $paginate,
            limit: $limit,
            sectionId: $sectionId,
        );
    }

    protected function component(): string
    {
        return 'news.list';
    }
}
