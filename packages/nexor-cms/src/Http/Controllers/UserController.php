<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Http\Requests\StoreUserRequest;
use Nexor\Cms\Http\Requests\UpdateUserRequest;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Uploads;

/**
 * The user model belongs to the host application, so every lookup here goes
 * through `Nexor::userModel()` and route parameters arrive via the explicit
 * `user` binding registered by the service provider.
 */
class UserController extends Controller
{
    /** @var array<int, string> */
    protected const SORTABLE = ['name', 'email', 'created_at', 'last_login_at'];

    public function index(Request $request): View
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'created_at';
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $users = Nexor::newUser()->newQuery()
            ->with('roles')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->when($request->filled('role'), fn ($query) => $query->whereHas(
                'roles',
                fn ($q) => $q->where('roles.id', $request->integer('role')),
            ))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->get('status') === 'active'))
            ->orderBy($sort, $direction)
            ->paginate(Nexor::perPage())
            ->withQueryString();

        $roles = Role::query()->ordered()->get();

        return view('nexor::admin.users.index', compact('users', 'roles'));
    }

    public function create(): View
    {
        $class = Nexor::userModel();

        return view('nexor::admin.users.form', [
            'user' => new $class(['is_active' => true]),
            'roles' => Role::query()->ordered()->get(),
            'selectedRoles' => [],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $class = Nexor::userModel();

        /** @var Model&NexorUser $user */
        $user = new $class($request->safe()->except(['avatar', 'roles']));
        $user->avatar = Uploads::handle($request, 'avatar', null, Nexor::directory('avatars'));
        $user->save();

        $user->roles()->sync($request->input('roles', []));

        ActivityLogger::created($user);

        return redirect()->route('admin.users.index')
            ->with('success', 'Пользователь «'.$user->name.'» создан.');
    }

    /**
     * @param  Model&NexorUser  $user
     */
    public function show(NexorUser $user): View
    {
        $user->load('roles.permissions');

        return view('nexor::admin.users.show', compact('user'));
    }

    /**
     * @param  Model&NexorUser  $user
     */
    public function edit(NexorUser $user): View
    {
        return view('nexor::admin.users.form', [
            'user' => $user,
            'roles' => Role::query()->ordered()->get(),
            'selectedRoles' => $user->roles->pluck('id')->all(),
        ]);
    }

    /**
     * @param  Model&NexorUser  $user
     */
    public function update(UpdateUserRequest $request, NexorUser $user): RedirectResponse
    {
        $data = $request->safe()->except(['avatar', 'avatar_remove', 'roles', 'password']);

        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        $user->fill($data);
        $user->avatar = Uploads::handle($request, 'avatar', $user->avatar, Nexor::directory('avatars'));

        // A user must never be able to lock themselves out of the panel.
        if ($user->is($request->user())) {
            $user->is_active = true;
        }

        $user->save();

        if (! $user->is($request->user())) {
            $user->roles()->sync($request->input('roles', []));
        }

        ActivityLogger::updated($user);

        return redirect()->route('admin.users.index')
            ->with('success', 'Пользователь «'.$user->name.'» сохранён.');
    }

    /**
     * @param  Model&NexorUser  $user
     */
    public function destroy(Request $request, NexorUser $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Нельзя удалить собственную учётную запись.');
        }

        ActivityLogger::deleted($user);
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Пользователь удалён.');
    }
}
