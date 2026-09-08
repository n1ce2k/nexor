<?php

namespace Nexor\Cms\View\Components;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Nexor\Cms\Models\Iblock;

/**
 * Постраничная навигация.
 *
 * ```blade
 * <x-nexor::pagination :paginator="$elements" :iblock="$iblock" />
 * <x-nexor::pagination :paginator="$elements" template="btnload" />
 * ```
 *
 * Шаблон по умолчанию задаёт сам инфоблок — поле «Шаблон» в его настройках;
 * проп `template` перебивает эту настройку для конкретного вызова. Файлы при
 * этом никогда не перезаписываются: настройка меняет только имя шаблона.
 */
class Pagination extends Component
{
    /**
     * @param  LengthAwarePaginator<int, mixed>|null  $paginator
     * @param  Iblock|null  $iblock  Откуда взять шаблон и «Показать ещё»
     * @param  string|null  $template  Перебивает настройку инфоблока
     * @param  bool|null  $loadMore  Перебивает переключатель «Показать ещё»
     */
    public function __construct(
        public ?LengthAwarePaginator $paginator = null,
        public ?Iblock $iblock = null,
        public ?string $template = null,
        public ?bool $loadMore = null,
    ) {}

    public function render(): View
    {
        $template = $this->template
            ?? $this->iblock?->pagination_template?->template()
            ?? 'default';

        return $this->template($template, [
            // Не `loadMore`: одноимённый публичный проп перекрыл бы значение.
            'showLoadMore' => $this->loadMore ?? (bool) $this->iblock?->has_load_more,
        ]);
    }

    protected function component(): string
    {
        return 'pagination';
    }
}
