<?php

namespace Nexor\Cms\Support;

use Nexor\Cms\Enums\MenuItemType;
use Nexor\Cms\Models\Menu;

/**
 * Куда можно вложить пункт меню.
 *
 * Вкладывать можно только в «Подменю» и не глубже, чем оно разрешает. Глубина
 * проверяется по всей цепочке подменю сверху: подменю внутри подменю не даёт
 * обойти лимит внешнего.
 *
 * Пункты, вложенные друг в друга до появления «Подменю», продолжают работать:
 * правила проверяются только у того, кого переносят.
 */
class MenuNesting
{
    /**
     * @param  array<int|string, array{parent: int|null, type: MenuItemType, max_depth: int}>  $items
     */
    public function __construct(protected array $items) {}

    /**
     * Карта пунктов меню: родитель, тип и глубина каждого.
     */
    public static function of(Menu $menu): self
    {
        $items = [];

        foreach ($menu->items()->get(['id', 'parent_id', 'type', 'max_depth']) as $item) {
            $items[$item->id] = [
                'parent' => $item->parent_id,
                'type' => $item->type,
                'max_depth' => (int) $item->max_depth,
            ];
        }

        return new self($items);
    }

    /**
     * Та же карта, но с родителями после перетаскивания.
     *
     * @param  array<int, int|null>  $parents  id пункта → новый родитель
     */
    public function moved(array $parents): self
    {
        $items = $this->items;

        foreach ($parents as $id => $parent) {
            if (isset($items[$id])) {
                $items[$id]['parent'] = $parent;
            }
        }

        return new self($items);
    }

    /**
     * Добавляет в карту пункт, которого ещё нет в базе: новый или правленый.
     */
    public function with(int|string $id, ?int $parent, MenuItemType $type, int $maxDepth): self
    {
        $items = $this->items;
        $items[$id] = ['parent' => $parent, 'type' => $type, 'max_depth' => $maxDepth];

        return new self($items);
    }

    public function parentOf(int|string $id): ?int
    {
        return $this->items[$id]['parent'] ?? null;
    }

    /**
     * Почему пункт не может лежать там, где лежит; null — может.
     */
    public function problem(int|string $id): ?string
    {
        $parent = $this->parentOf($id);

        if ($parent === null) {
            return null;
        }

        if (! isset($this->items[$parent])) {
            return 'Родительский пункт не найден.';
        }

        if (! $this->items[$parent]['type']->acceptsChildren()) {
            return 'В такой пункт ничего вложить нельзя.';
        }

        $height = $this->height($id);
        $level = 0;
        $inside = false;

        // Поднимаемся от пункта к корню: у каждого подменю по дороге своя глубина.
        for ($current = $parent, $guard = 0; $current !== null && $guard < 100; $guard++) {
            if ($current === $id) {
                return 'Пункт нельзя вложить в его же подпункт.';
            }

            $level++;
            $node = $this->items[$current] ?? null;

            if ($node === null) {
                break;
            }

            if ($node['type'] === MenuItemType::Submenu) {
                $inside = true;

                if ($level + $height > max(1, $node['max_depth'])) {
                    return 'Не помещается: глубина подменю — '.max(1, $node['max_depth']).'.';
                }
            }

            $current = $node['parent'];
        }

        return $inside ? null : 'Вложить пункт можно только в «Подменю».';
    }

    /**
     * Сколько уровней под пунктом: 0 — детей нет.
     */
    protected function height(int|string $id, int $guard = 0): int
    {
        $height = 0;

        foreach ($this->items as $child => $item) {
            if ($item['parent'] === $id && $guard < 100) {
                $height = max($height, 1 + $this->height($child, $guard + 1));
            }
        }

        return $height;
    }
}
