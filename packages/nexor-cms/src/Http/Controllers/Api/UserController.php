<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Http\Requests\StoreUserRequest;
use Nexor\Cms\Http\Requests\UpdateUserRequest;
use Nexor\Cms\Http\Resources\UserResource;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Uploads;

class UserController extends ApiController
{
    /** @var array<int, string> */
    protected const SORTABLE = ['name', 'email', 'created_at', 'last_login_at'];

    public function index(Request $request): AnonymousResourceCollection
    {
        [$column, $direction] = $this->sorting($request, self::SORTABLE, 'created_at', 'desc');

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
            ->orderBy($column, $direction)
            ->paginate($this->perPage($request));

        return UserResource::collection($users);
    }

    /**
     * @param  Model&NexorUser  $user
     */
    public function show(NexorUser $user): UserResource
    {
        return UserResource::make($user->load('roles'));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $class = Nexor::userModel();

        /** @var Model&NexorUser $user */
        $user = new $class($request->safe()->except(['avatar', 'roles']));
        $user->avatar = Uploads::handle($request, 'avatar', null, Nexor::directory('avatars'));
        $user->save();

        $user->roles()->sync($request->input('roles', []));

        ActivityLogger::created($user);

        return UserResource::make($user->load('roles'))
            ->additional(['message' => 'Пользователь «'.$user->name.'» создан.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @param  Model&NexorUser  $user
     */
    public function update(UpdateUserRequest $request, NexorUser $user): JsonResponse
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

        return UserResource::make($user->fresh('roles'))
            ->additional(['message' => 'Пользователь «'.$user->name.'» сохранён.'])
            ->response();
    }

    /**
     * @param  Model&NexorUser  $user
     */
    public function destroy(Request $request, NexorUser $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return $this->refuse('Нельзя удалить собственную учётную запись.');
        }

        ActivityLogger::deleted($user);
        $user->delete();

        return $this->ok('Пользователь удалён.');
    }
}
