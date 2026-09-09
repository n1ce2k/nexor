<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Http\Requests\MenuItemRequest;
use Nexor\Cms\Http\Resources\MenuItemResource;
use Nexor\Cms\Models\Menu;
use Nexor\Cms\Models\MenuItem;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\MenuResolver;

class MenuItemController extends ApiController
{
    public function store(MenuItemRequest $request, Menu $menu): JsonResponse
    {
        $item = new MenuItem($request->validated());
        $item->menu_id = $menu->id;
        $item->sort = $item->sort ?: $this->nextSort($menu, $item->parent_id);
        $item->save();

        ActivityLogger::created($item, "Пункт меню «{$menu->name}»");

        return MenuItemResource::make($item)
            ->additional(['message' => 'Пункт добавлен.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(MenuItemRequest $request, Menu $menu, MenuItem $item): JsonResponse
    {
        abort_unless($item->menu_id === $menu->id, 404);

        $item->update($request->validated());

        ActivityLogger::updated($item, "Пункт меню «{$menu->name}»");

        return MenuItemResource::make($item->fresh())
            ->additional(['message' => 'Пункт сохранён.'])
            ->response();
    }

    public function destroy(Menu $menu, MenuItem $item): JsonResponse
    {
        abort_unless($item->menu_id === $menu->id, 404);

        ActivityLogger::deleted($item, "Пункт меню «{$menu->name}»");
        $item->delete();

        return $this->ok('Пункт и всё вложенное удалены.');
    }

    /**
     * Новый порядок и вложенность после перетаскивания.
     *
     * Экран присылает дерево целиком, потому что одно перетаскивание меняет и
     * родителя, и порядок сразу у нескольких пунктов. Разбирать это по одному
     * запросу на пункт — лишние круги и рассинхрон на полпути.
     */
    public function reorder(Request $request, Menu $menu): JsonResponse
    {
        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.parent_id' => ['nullable', 'integer'],
        ]);

        $own = $menu->items()->pluck('id')->all();
        $positions = [];

        foreach (array_values($data['items']) as $index => $row) {
            // Чужой пункт в чужом меню переставить нельзя.
            if (! in_array($row['id'], $own, true)) {
                continue;
            }

            $parent = $row['parent_id'] ?? null;

            $positions[$row['id']] = [
                'parent_id' => in_array($parent, $own, true) ? $parent : null,
                'sort' => ($index + 1) * 10,
            ];
        }

        foreach ($positions as $id => $position) {
            MenuItem::query()->whereKey($id)->update($position);
        }

        // Массовое обновление проходит мимо событий модели, а значит и мимо сброса кеша.
        MenuResolver::forget();

        ActivityLogger::updated($menu, "Порядок пунктов меню «{$menu->name}»");

        return response()->json([
            'items' => MenuItemResource::collection(
                $menu->items()->with(['element', 'section', 'iblock'])->get(),
            ),
            'message' => 'Порядок сохранён.',
        ]);
    }

    protected function nextSort(Menu $menu, ?int $parentId): int
    {
        return (int) $menu->items()->where('parent_id', $parentId)->max('sort') + 10;
    }
}
