<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Nexor\Cms\Http\Requests\StoreRoleRequest;
use Nexor\Cms\Http\Requests\UpdateRoleRequest;
use Nexor\Cms\Models\Permission;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\ActivityLogger;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->ordered()
            ->paginate(20);

        return view('nexor::admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('nexor::admin.roles.form', [
            'role' => new Role(['sort' => 500]),
            'groups' => $this->permissionGroups(),
            'selected' => [],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create($request->safe()->except('permissions'));
        $role->permissions()->sync($request->input('permissions', []));

        ActivityLogger::created($role);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Роль «'.$role->name.'» создана.');
    }

    public function show(Role $role): RedirectResponse
    {
        return redirect()->route('admin.roles.edit', $role);
    }

    public function edit(Role $role): View
    {
        return view('nexor::admin.roles.form', [
            'role' => $role,
            'groups' => $this->permissionGroups(),
            'selected' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->fill($request->safe()->except(['permissions', 'code']));

        if (! $role->is_system) {
            $role->code = $request->input('code');
        }

        $role->save();

        // The super admin role always holds every permission, including new ones.
        if ($role->isSuperAdmin()) {
            $role->permissions()->sync(Permission::query()->pluck('id'));
        } else {
            $role->permissions()->sync($request->input('permissions', []));
        }

        ActivityLogger::updated($role);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Роль «'.$role->name.'» сохранена.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'Системную роль удалить нельзя.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Роль назначена пользователям — сначала снимите её.');
        }

        ActivityLogger::deleted($role);
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Роль удалена.');
    }

    /**
     * Permissions grouped for the checkbox matrix, content groups last.
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    protected function permissionGroups(): Collection
    {
        return Permission::query()
            ->ordered()
            ->get()
            ->groupBy(fn (Permission $permission) => $permission->group_label ?? $permission->group);
    }
}
