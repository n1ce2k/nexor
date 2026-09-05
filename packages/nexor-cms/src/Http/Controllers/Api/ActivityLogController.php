<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Nexor\Cms\Http\Resources\ActivityLogResource;
use Nexor\Cms\Models\ActivityLog;
use Nexor\Cms\Support\Nexor;

class ActivityLogController extends ApiController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->filled('search'), fn ($query) => $query->where(
                'description', 'like', '%'.$request->string('search')->trim().'%',
            ))
            ->when($request->filled('user'), fn ($query) => $query->where('user_id', $request->integer('user')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->get('action')))
            ->latestFirst()
            ->paginate($this->perPage($request, 'logs'));

        return ActivityLogResource::collection($logs)->additional([
            'meta' => [
                'users' => Nexor::newUser()->newQuery()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($user) => ['value' => $user->id, 'label' => $user->name]),
                'actions' => [
                    ['value' => 'created', 'label' => 'Создание'],
                    ['value' => 'updated', 'label' => 'Изменение'],
                    ['value' => 'deleted', 'label' => 'Удаление'],
                    ['value' => 'login', 'label' => 'Вход'],
                    ['value' => 'logout', 'label' => 'Выход'],
                    ['value' => 'login_failed', 'label' => 'Неудачный вход'],
                ],
            ],
        ]);
    }
}
