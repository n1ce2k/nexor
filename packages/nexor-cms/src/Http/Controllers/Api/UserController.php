<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Http\Requests\StoreUserRequest;
use Nexor\Cms\Http\Requests\UpdateUserRequest;
use Nexor\Cms\Http\Resources\UserFieldResource;
use Nexor\Cms\Http\Resources\UserResource;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Uploads;
use Nexor\Cms\Support\UserFields;

class UserController extends ApiController
{
    /** @var array<int, string> */
    protected const SORTABLE = ['name', 'email', 'created_at', 'last_login_at'];

    public function index(Request $request): AnonymousResourceCollection
    {
        [$column, $direction] = $this->sorting($request, self::SORTABLE, 'created_at', 'desc');

        $users = Nexor::newUser()->newQuery()
            ->with(['roles', 'fieldValues.field'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(fn ($q) => $q->where('name', 'like', $search)
                    ->orWhere('login', 'like', $search)
                    ->orWhere('email', 'like', $search));
            })
            ->when($request->filled('role'), fn ($query) => $query->whereHas(
                'roles',
                fn ($q) => $q->where('roles.id', $request->integer('role')),
            ))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->get('status') === 'active'))
            ->tap(fn ($query) => UserFields::filter($query, (array) $request->input('fields', [])))
            ->orderBy($column, $direction)
            ->paginate($this->perPage($request));

        return UserResource::collection($users);
    }

    /**
     * Свои поля, из которых панель строит карточку пользователя.
     */
    public function schema(): JsonResponse
    {
        return response()->json([
            'fields' => UserFieldResource::collection(UserFields::all())->resolve(),
        ]);
    }

    /**
     * @param  Model&NexorUser  $user
     */
    public function show(NexorUser $user): UserResource
    {
        return UserResource::make($user->load(['roles', 'fieldValues.field']));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $class = Nexor::userModel();

        /** @var Model&NexorUser $user */
        $user = new $class($request->safe()->except(['avatar', 'roles', 'fields', 'field_files', 'field_remove']));
        $user->avatar = Uploads::handle($request, 'avatar', null, Nexor::directory('avatars'));
        $user->save();

        $user->roles()->sync($request->input('roles', []));
        UserFields::save($user, $request);

        ActivityLogger::created($user);

        return UserResource::make($user->load(['roles', 'fieldValues.field']))
            ->additional(['message' => 'Пользователь «'.$user->name.'» создан.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @param  Model&NexorUser  $user
     */
    public function update(UpdateUserRequest $request, NexorUser $user): JsonResponse
    {
        $data = $request->safe()->except(['avatar', 'avatar_remove', 'roles', 'password', 'fields', 'field_files', 'field_remove']);

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

        UserFields::save($user, $request);

        ActivityLogger::updated($user);

        return UserResource::make($user->fresh(['roles', 'fieldValues.field']))
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
