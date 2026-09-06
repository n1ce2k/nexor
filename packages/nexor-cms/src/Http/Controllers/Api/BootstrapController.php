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
            'routes' => [
                'home' => url('/'),
                'logout' => route('admin.logout'),
                'classic' => Nexor::otherPanel(),
            ],
        ]);
    }
}
