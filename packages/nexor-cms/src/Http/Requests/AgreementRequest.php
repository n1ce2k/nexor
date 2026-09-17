<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->route('agreement') ? 'agreements.update' : 'agreements.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('agreements', 'code')->ignore($this->route('agreement')?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:500'],
            'link_text' => ['nullable', 'string', 'max:255'],
            'text' => ['nullable', 'string', 'max:500000'],
            'text_type' => ['required', 'in:html,text'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
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
            'label' => 'текст у галочки',
            'link_text' => 'текст ссылки',
            'text' => 'текст соглашения',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Символьный код — латиница в нижнем регистре, цифры, дефис и подчёркивание.',
        ];
    }
}
