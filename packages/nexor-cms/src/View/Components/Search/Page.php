<?php

namespace Nexor\Cms\View\Components\Search;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\View\Components\Component;

/**
 * Результаты поиска — аналог `search.page`.
 *
 * ```blade
 * <x-nexor::search.page />                                    по всем инфоблокам
 * <x-nexor::search.page :iblocks="['katalog', 'news']" />
 * ```
 *
 * Запрос берётся из `?q=`. Пока ищем по всем инфоблокам сразу, результаты
 * сгруппированы и каждая группа обрезана: свести их в один постраничный список
 * нельзя, элементы лежат в разных инфоблоках. Ссылка «показать все» добавляет
 * `?in=<код>`, и тогда этот инфоблок выводится постранично.
 */
class Page extends Component
{
    /**
     * @param  string  $template  Имя шаблона вёрстки
     * @param  array<int, string>  $iblocks  Где искать; пусто — во всех активных
     * @param  int  $limit  Сколько показывать в группе
     * @param  int|null  $perPage  Размер страницы, когда выбран один инфоблок
     */
    public function __construct(
        public string $template = 'default',
        public array $iblocks = [],
        public int $limit = 5,
        public ?int $perPage = null,
    ) {}

    public function render(): View
    {
        $query = trim((string) request()->query('q', ''));
        $only = (string) request()->query('in', '');

        return $this->template($this->template, [
            'query' => $query,
            'groups' => $query === '' ? [] : $this->groups($query, $only),
            'only' => $only,
            'searched' => request()->has('q'),
        ]);
    }

    protected function component(): string
    {
        return 'search.page';
    }

    /**
     * Результаты по инфоблокам.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function groups(string $query, string $only): array
    {
        $groups = [];

        foreach ($this->scope($only) as $block) {
            $paged = $only === $block->code;

            $found = $this->iblocks()->searchElements(
                $block->code,
                $query,
                ['is_active' => true],
                perPage: $paged ? ($this->perPage ?? $block->pageSize()) : null,
                limit: $paged ? null : $this->limit + 1,
            );

            if ($found->isEmpty()) {
                continue;
            }

            // Лишний элемент нужен только чтобы понять, есть ли что-то ещё.
            $more = ! $paged && $found->count() > $this->limit;

            $groups[] = [
                'iblock' => $block,
                'name' => $block->name,
                'code' => $block->code,
                'elements' => $paged ? $found : $found->take($this->limit),
                'paged' => $paged,
                'more' => $more,
                'url' => url()->current().'?'.http_build_query(['q' => $query, 'in' => $block->code]),
            ];
        }

        return $groups;
    }

    /**
     * @return Collection<int, Iblock>
     */
    protected function scope(string $only): Collection
    {
        $codes = $only !== '' ? [$only] : $this->iblocks;

        $query = Iblock::query()->active()->ordered();

        if ($codes !== []) {
            $query->whereIn('code', $codes);
        }

        return $query->get();
    }
}
