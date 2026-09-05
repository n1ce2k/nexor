<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Nexor\Cms\Models\ActivityLog;
use Nexor\Cms\Support\Nexor;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->filled('search'), fn ($query) => $query->where(
                'description', 'like', '%'.$request->string('search')->trim().'%',
            ))
            ->when($request->filled('user'), fn ($query) => $query->where('user_id', $request->integer('user')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->get('action')))
            ->latestFirst()
            ->paginate(50)
            ->withQueryString();

        $users = Nexor::newUser()->newQuery()->orderBy('name')->pluck('name', 'id');

        $actions = [
            'created' => 'Создание',
            'updated' => 'Изменение',
            'deleted' => 'Удаление',
            'login' => 'Вход',
            'logout' => 'Выход',
            'login_failed' => 'Неудачный вход',
        ];

        return view('nexor::admin.logs.index', compact('logs', 'users', 'actions'));
    }
}
