<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Nexor\Cms\Enums\MenuItemType;
use Nexor\Cms\Enums\MenuVisibility;
use Nexor\Cms\Http\Requests\MenuRequest;
use Nexor\Cms\Http\Resources\MenuResource;
use Nexor\Cms\Models\Menu;
use Nexor\Cms\Support\ActivityLogger;

class MenuController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        return MenuResource::collection(
            Menu::query()->withCount('items')->ordered()->get(),
        );
    }

    public function show(Menu $menu): MenuResource
    {
        return MenuResource::make($menu->load('items.element', 'items.section', 'items.iblock'));
    }

    public function store(MenuRequest $request): JsonResponse
    {
        $menu = Menu::query()->create($request->validated());

        ActivityLogger::created($menu, "Меню «{$menu->name}»");

        return MenuResource::make($menu)
            ->additional(['message' => 'Меню «'.$menu->name.'» создано.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(MenuRequest $request, Menu $menu): JsonResponse
    {
        $menu->update($request->validated());

        ActivityLogger::updated($menu, "Меню «{$menu->name}»");

        return MenuResource::make($menu->fresh())
            ->additional(['message' => 'Меню «'.$menu->name.'» сохранено.'])
            ->response();
    }

    public function destroy(Menu $menu): JsonResponse
    {
        ActivityLogger::deleted($menu, "Меню «{$menu->name}»");
        $menu->delete();

        return $this->ok('Меню и все его пункты удалены.');
    }

    /**
     * Справочники для формы пункта: типы, видимость, глубина.
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'types' => MenuItemType::options(),
            'visibility' => MenuVisibility::options(),
        ]);
    }
}
