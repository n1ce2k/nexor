<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\View\View;
use Nexor\Cms\Models\ActivityLog;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\Nexor;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $stats = [
            [
                'label' => 'Пользователи',
                'value' => Nexor::newUser()->newQuery()->count(),
                'icon' => 'users',
                'url' => $user->hasPermission('users.view') ? route('admin.users.index') : null,
            ],
            [
                'label' => 'Роли',
                'value' => Role::query()->count(),
                'icon' => 'shield',
                'url' => $user->hasPermission('roles.view') ? route('admin.roles.index') : null,
            ],
            [
                'label' => 'Инфоблоки',
                'value' => Iblock::query()->count(),
                'icon' => 'layers',
                'url' => $user->hasPermission('iblocks.view') ? route('admin.iblocks.index') : null,
            ],
            [
                'label' => 'Элементов всего',
                'value' => IblockElement::query()->count(),
                'icon' => 'document',
                'url' => null,
            ],
        ];

        $iblocks = Iblock::query()
            ->active()
            ->with('type')
            ->withCount('elements')
            ->ordered()
            ->get()
            ->filter(fn (Iblock $iblock) => $user->hasPermission($iblock->permissionCode('view')));

        $recent = $user->hasPermission('logs.view')
            ? ActivityLog::query()->with('user')->latestFirst()->limit(10)->get()
            : collect();

        return view('nexor::admin.dashboard', compact('stats', 'iblocks', 'recent'));
    }
}
