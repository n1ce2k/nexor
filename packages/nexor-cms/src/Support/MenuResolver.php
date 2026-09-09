<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Nexor\Cms\Enums\MenuItemType;
use Nexor\Cms\Enums\MenuVisibility;
use Nexor\Cms\Models\Menu;
use Nexor\Cms\Models\MenuItem;
use Nexor\Cms\Services\InfoBlockService;

/**
 * Собирает меню в дерево, готовое к выводу.
 *
 * Работа в два шага, и это не случайно. Сначала строится дерево, не зависящее
 * от текущего адреса, — его можно закешировать. Потом по этому дереву проходит
 * подсветка активного пункта, которая у каждой страницы своя. Смешивать нельзя:
 * закешированная подсветка подсветит не ту страницу.
 */
class MenuResolver
{
    /** Сколько держать собранное дерево. */
    public const TTL = 3600;

    public function __construct(protected InfoBlockService $iblocks) {}

    /**
     * Дерево пунктов меню с подсветкой текущей страницы.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(string $code, ?string $currentUrl = null): array
    {
        // Порядок важен: из кеша приходит общее дерево, а видимость и подсветка
        // зависят от того, кто смотрит и какую страницу — их кешировать нельзя.
        return $this->highlight($this->visible($this->cached($code)), $currentUrl ?? url()->full());
    }

    /**
     * Убирает пункты, которые этому посетителю не предназначены.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @return array<int, array<string, mixed>>
     */
    public function visible(array $tree): array
    {
        $user = auth()->user();

        $kept = [];

        foreach ($tree as $node) {
            $visibility = $node['visibility'];

            if ($visibility instanceof MenuVisibility && ! $visibility->allows($user)) {
                continue;
            }

            $node['children'] = $this->visible($node['children'] ?? []);
            $kept[] = $node;
        }

        return $kept;
    }

    /**
     * Дерево без подсветки — то, что можно держать в кеше.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cached(string $code): array
    {
        // Кеш выключается конфигом: на разработке приятнее видеть правки сразу.
        if (! config('nexor.menu.cache', true)) {
            return $this->build($code);
        }

        return Cache::remember(
            self::cacheKey($code),
            config('nexor.menu.ttl', self::TTL),
            fn () => $this->build($code),
        );
    }

    public static function cacheKey(string $code): string
    {
        return 'nexor.menu.'.$code;
    }

    /**
     * Сбросить собранное дерево — после правки меню или разделов-источников.
     */
    public static function forget(?string $code = null): void
    {
        if ($code !== null) {
            Cache::forget(self::cacheKey($code));

            return;
        }

        Menu::query()->pluck('code')->each(fn (string $one) => Cache::forget(self::cacheKey($one)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function build(string $code): array
    {
        $menu = Menu::query()->active()->where('code', $code)->first();

        if (! $menu) {
            return [];
        }

        $items = $menu->items()->active()->with(['element.iblock', 'section', 'iblock'])->get();

        return $this->branch($items, null, 1);
    }

    /**
     * Ветка дерева: пункты одного уровня со своими детьми.
     *
     * @param  Collection<int, MenuItem>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function branch(Collection $items, ?int $parentId, int $level): array
    {
        $branch = [];

        foreach ($items->where('parent_id', $parentId) as $item) {
            if ($item->type->isDynamic()) {
                // Динамический пункт не рисуется сам — он раскрывается в ветку
                // на своё место, продолжая нумерацию уровней.
                array_push($branch, ...$this->expand($item, $level));

                continue;
            }

            $branch[] = $this->node($item, $level) + [
                'children' => $item->type->acceptsChildren()
                    ? $this->branch($items, $item->id, $level + 1)
                    : [],
            ];
        }

        return $branch;
    }

    /**
     * Один статический пункт.
     *
     * @return array<string, mixed>
     */
    protected function node(MenuItem $item, int $level): array
    {
        return [
            'id' => $item->id,
            'kind' => $item->type->value,
            'name' => $this->title($item),
            'url' => $this->url($item),
            'level' => $level,
            'target' => $item->target,
            'class' => $item->css_class,
            'icon' => $item->icon,
            'visibility' => $item->visibility,
            'highlight_children' => $item->highlight_children,
            'active' => false,
            'open' => false,
        ];
    }

    /**
     * Заголовок: свой, если задан, иначе имя привязанной сущности.
     */
    protected function title(MenuItem $item): string
    {
        if (filled($item->title)) {
            return $item->title;
        }

        return match ($item->type) {
            MenuItemType::Page => $item->element?->name ?? '',
            MenuItemType::Section => $item->section?->name ?? '',
            default => '',
        };
    }

    /**
     * Адрес пункта.
     *
     * У страниц и разделов он считается из сущности, поэтому переименование
     * символьного кода не оставляет в меню битую ссылку.
     */
    protected function url(MenuItem $item): ?string
    {
        return match ($item->type) {
            MenuItemType::Link => $item->url,
            MenuItemType::Page => $item->element?->url(),
            MenuItemType::Section => $item->section && $item->iblock
                ? url('/'.$item->iblock->code.'?section='.$item->section->code)
                : null,
            default => null,
        };
    }

    /**
     * Раскрывает динамический пункт в дерево разделов инфоблока.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function expand(MenuItem $item, int $level): array
    {
        $iblock = $item->iblock;

        if (! $iblock || ! $iblock->is_active || ! $iblock->has_sections) {
            return [];
        }

        $sections = $this->iblocks->getSections($iblock->code)->keyBy('id');
        $root = $item->section_id ? $sections->get($item->section_id) : null;

        if ($item->section_id && ! $root) {
            return [];
        }

        // Уровень считается от единицы, и когда корень задан, и когда нет, иначе
        // выбранный раздел получает ноль и его дети встают рядом, а не внутрь.
        $base = $root ? $root->depth - 1 : -1;
        $nodes = [];

        foreach ($sections as $section) {
            if ($root && ! $this->inside($section->path, $section->id, $root->path, $root->id)) {
                continue;
            }

            $own = $section->depth - $base;

            if ($own > max(1, $item->max_depth)) {
                continue;
            }

            $nodes[$section->id] = [
                'id' => 'section-'.$section->id,
                'parent' => $section->parent_id,
                'kind' => 'section',
                'name' => $section->name,
                'url' => url('/'.$iblock->code.'?section='.$section->code),
                'level' => $level + $own - 1,
                'target' => null,
                'class' => null,
                'icon' => null,
                'visibility' => $item->visibility,
                'highlight_children' => $item->highlight_children,
                'active' => false,
                'open' => false,
                'children' => [],
            ];
        }

        return $this->nest($nodes, $root?->id);
    }

    protected function inside(string $path, int $id, string $rootPath, int $rootId): bool
    {
        return $id === $rootId || str_starts_with($path, $rootPath.$rootId.'/');
    }

    /**
     * Плоский список разделов в дерево по `parent_id`.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function nest(array $nodes, ?int $rootId): array
    {
        $tree = [];

        foreach ($nodes as $id => &$node) {
            $parent = $node['parent'];

            // Заданный корень сам попадает в дерево, поэтому его дети цепляются к нему.
            if ($parent && isset($nodes[$parent])) {
                $nodes[$parent]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }

        unset($node);

        return $tree;
    }

    // ------------------------------------------------------------- подсветка

    /**
     * Отмечает пункт текущей страницы и его родителей.
     *
     * Выигрывает самое длинное совпадение: на `/katalog/razdel1` подсветится
     * «Раздел 1», а не «Каталог», хотя адрес начинается с него. Родителям
     * достаётся `open` — по нему шаблон раскрывает ветку.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @return array<int, array<string, mixed>>
     */
    public function highlight(array $tree, string $currentUrl): array
    {
        $current = $this->normalise($currentUrl);
        $best = null;
        $bestLength = -1;

        $this->walk($tree, function (array &$node, array $ancestors) use ($current, &$best, &$bestLength): void {
            $url = $node['url'] ? $this->normalise($node['url']) : null;

            if ($url === null || ! $this->matches($current, $url, $node['highlight_children'])) {
                return;
            }

            if (strlen($url) > $bestLength) {
                $bestLength = strlen($url);
                $best = [...$ancestors, $node['id']];
            }
        });

        if ($best === null) {
            return $tree;
        }

        $winner = array_pop($best);

        $this->walk($tree, function (array &$node) use ($best, $winner): void {
            if ($node['id'] === $winner) {
                $node['active'] = true;
                $node['open'] = true;
            } elseif (in_array($node['id'], $best, true)) {
                $node['open'] = true;
            }
        });

        return $tree;
    }

    /**
     * Совпадает ли адрес пункта с текущим.
     *
     * Точное совпадение — всегда. Вложенные страницы — только если пункт это
     * разрешает, и только по границе сегмента: `/katalog` не должен ловить
     * `/katalogi`.
     */
    protected function matches(string $current, string $url, bool $withChildren): bool
    {
        if ($current === $url) {
            return true;
        }

        if (! $withChildren || $url === '/') {
            return false;
        }

        // У пункта с параметрами (`?section=`) вложенности не бывает.
        if (str_contains($url, '?')) {
            return false;
        }

        return str_starts_with($this->path($current), rtrim($url, '/').'/');
    }

    /**
     * Адрес в сравнимом виде: без домена, без хвостового слэша.
     */
    protected function normalise(string $url): string
    {
        $parts = parse_url($url);
        $path = rtrim($parts['path'] ?? '/', '/');

        return ($path === '' ? '/' : $path).(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    protected function path(string $url): string
    {
        return explode('?', $url)[0];
    }

    /**
     * Обходит дерево, отдавая каждому узлу список его предков.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @param  array<int, mixed>  $ancestors
     */
    protected function walk(array &$tree, callable $callback, array $ancestors = []): void
    {
        foreach ($tree as &$node) {
            $callback($node, $ancestors);

            if ($node['children'] ?? []) {
                $this->walk($node['children'], $callback, [...$ancestors, $node['id']]);
            }
        }

        unset($node);
    }
}
