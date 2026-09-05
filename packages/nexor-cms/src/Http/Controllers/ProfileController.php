<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Nexor\Cms\Http\Requests\ProfileRequest;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Uploads;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('nexor::admin.profile.edit', ['user' => $request->user()->load('roles')]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->except(['avatar', 'avatar_remove']));
        $user->avatar = Uploads::handle($request, 'avatar', $user->avatar, 'avatars');
        $user->save();

        ActivityLogger::updated($user, 'Профиль обновлён');

        return back()->with('success', 'Профиль сохранён.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], [
            'current_password' => 'текущий пароль',
            'password' => 'новый пароль',
        ]);

        $request->user()->forceFill(['password' => Hash::make($validated['password'])])->save();

        ActivityLogger::log('updated', $request->user(), 'Смена пароля');

        return back()->with('success', 'Пароль изменён.');
    }
}
