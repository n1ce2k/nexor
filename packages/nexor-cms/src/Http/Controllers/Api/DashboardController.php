<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Http\Resources\ActivityLogResource;
use Nexor\Cms\Http\Resources\IblockResource;
use Nexor\Cms\Models\ActivityLog;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\Nexor;

class DashboardController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $iblocks = Iblock::query()
            ->active()
            ->with('type')
            ->withCount('elements')
            ->ordered()
            ->get()
            ->filter(fn (Iblock $iblock) => $user->hasPermission($iblock->permissionCode('view')))
            ->values();

        return response()->json([
            'stats' => [
                [
                    'key' => 'users',
                    'label' => 'Пользователи',
                    'icon' => 'users',
                    'value' => Nexor::newUser()->newQuery()->count(),
                    'route' => $user->hasPermission('users.view') ? 'users.index' : null,
                ],
                [
                    'key' => 'roles',
                    'label' => 'Роли',
                    'icon' => 'shield',
                    'value' => Role::query()->count(),
                    'route' => $user->hasPermission('roles.view') ? 'roles.index' : null,
                ],
                [
                    'key' => 'iblocks',
                    'label' => 'Инфоблоки',
                    'icon' => 'layers',
                    'value' => Iblock::query()->count(),
                    'route' => $user->hasPermission('iblocks.view') ? 'iblocks.index' : null,
                ],
                [
                    'key' => 'elements',
                    'label' => 'Элементов всего',
                    'icon' => 'document',
                    'value' => IblockElement::query()->count(),
                    'route' => null,
                ],
            ],
            'iblocks' => IblockResource::collection($iblocks),
            'recent' => ActivityLogResource::collection(
                $user->hasPermission('logs.view')
                    ? ActivityLog::query()->with('user')->latestFirst()->limit(10)->get()
                    : collect(),
            ),
        ]);
    }
}
