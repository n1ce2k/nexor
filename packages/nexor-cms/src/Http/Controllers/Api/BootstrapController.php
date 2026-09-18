<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Enums\PaginationTemplate;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Http\Resources\IblockResource;
use Nexor\Cms\Http\Resources\UserResource;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\Licensing;
use Nexor\Cms\Support\Modules\ModuleFields;
use Nexor\Cms\Support\Nexor;

/**
 * Everything the SPA needs on first paint: who is signed in, what they may do,
 * the menu, and the catalogue of property types the field registry maps over.
 */
class BootstrapController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $state = Licensing::state();

        $iblocks = Iblock::query()
            ->active()
            ->with('type')
            ->withCount('elements')
            ->ordered()
            ->get()
            ->filter(fn (Iblock $iblock) => $user->hasPermission($iblock->permissionCode('view')))
            ->values();

        return response()->json([
            'user' => UserResource::make($user->loadMissing('roles')),
            'permissions' => $user->isSuperAdmin() ? ['*'] : $user->permissionCodes(),
            'is_super_admin' => $user->isSuperAdmin(),
            'brand' => [
                'name' => Nexor::brand('name'),
                'initial' => Nexor::brand('initial'),
                'site_name' => Setting::get('site.name', Nexor::brand('name')),
                'version' => Nexor::VERSION,
            ],
            'iblocks' => IblockResource::collection($iblocks),
            'property_types' => collect(Nexor::propertyTypes())->map(fn (PropertyType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'group' => $type->group(),
                'uses_enums' => $type->usesEnums(),
                'is_file' => $type->isFile(),
                'is_filterable' => $type->isFilterable(),
                'settings' => $type->settingKeys(),
            ])->values(),
            'pagination_templates' => PaginationTemplate::options(),
            'license' => [
                'value' => Nexor::license()->value,
                'label' => Nexor::license()->label(),
                // Состояние ключа: панель показывает плашку, если он не в порядке.
                'status' => $state['status'],
                'number' => $state['number'],
                'expires_at' => $state['expires_at'],
                'message' => $state['message'],
            ],
            // «Код функции или модуля → доступно»: по нему панель прячет меню и страницы.
            'features' => Nexor::modules()->allowed(),
            // Переключатели, которые включённые модули добавляют в форму инфоблока.
            'iblock_settings' => ModuleFields::iblockSettings(),
            'routes' => [
                'home' => url('/'),
                'logout' => route('admin.logout'),
                'classic' => Nexor::otherPanel(),
            ],
        ]);
    }
}
