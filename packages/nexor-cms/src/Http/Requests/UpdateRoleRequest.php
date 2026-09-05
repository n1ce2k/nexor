<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('roles.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            // System roles keep their code so permission checks in code stay valid.
            'code' => [
                $role->is_system ? 'nullable' : 'required',
                'string', 'max:190', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('roles', 'code')->ignore($role->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Символьный код может содержать только латиницу в нижнем регистре, цифры, дефис и подчёркивание.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'символьный код',
            'name' => 'название',
            'description' => 'описание',
            'sort' => 'сортировка',
            'permissions' => 'права',
        ];
    }
}
