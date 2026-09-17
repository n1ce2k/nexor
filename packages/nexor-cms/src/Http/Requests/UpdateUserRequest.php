<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Nexor\Cms\Support\UserFields;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('users.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'login')->ignore($userId)->withoutTrashed()],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)->withoutTrashed()],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'avatar_remove' => ['boolean'],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
            ...UserFields::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.regex' => 'Логин — латиница, цифры, точка, дефис и подчёркивание.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'имя',
            'login' => 'логин',
            'email' => 'e-mail',
            'password' => 'пароль',
            'phone' => 'телефон',
            'avatar' => 'аватар',
            'roles' => 'роли',
            ...UserFields::attributes(),
        ];
    }
}
