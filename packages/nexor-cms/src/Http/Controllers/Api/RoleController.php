<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Nexor\Cms\Http\Requests\StoreRoleRequest;
use Nexor\Cms\Http\Requests\UpdateRoleRequest;
use Nexor\Cms\Http\Resources\PermissionResource;
use Nexor\Cms\Http\Resources\RoleResource;
use Nexor\Cms\Models\Permission;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\ActivityLogger;

class RoleController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->ordered()
            ->paginate($this->perPage($request));

        // The permission catalogue travels with the list so the editor can open instantly.
        return RoleResource::collection($roles)->additional([
            'meta' => ['permissions' => PermissionResource::collection(Permission::query()->ordered()->get())],
        ]);
    }

    public function show(Role $role): RoleResource
    {
        return RoleResource::make($role->load('permissions')->loadCount(['users', 'permissions']));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::query()->create($request->safe()->except('permissions'));
        $role->permissions()->sync($request->input('permissions', []));

        ActivityLogger::created($role);

        return RoleResource::make($role->load('permissions'))
            ->additional(['message' => 'Роль «'.$role->name.'» создана.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role->fill($request->safe()->except(['permissions', 'code']));

        if (! $role->is_system) {
            $role->code = $request->input('code');
        }

        $role->save();

        // The super admin role always holds every permission, including new ones.
        $role->permissions()->sync($role->isSuperAdmin()
            ? Permission::query()->pluck('id')
            : $request->input('permissions', []));

        ActivityLogger::updated($role);

        return RoleResource::make($role->fresh('permissions'))
            ->additional(['message' => 'Роль «'.$role->name.'» сохранена.'])
            ->response();
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return $this->refuse('Системную роль удалить нельзя.');
        }

        if ($role->users()->exists()) {
            return $this->refuse('Роль назначена пользователям — сначала снимите её.');
        }

        ActivityLogger::deleted($role);
        $role->delete();

        return $this->ok('Роль удалена.');
    }
}
