<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('mail.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:190', 'regex:/^[A-Z0-9_]+$/',
                Rule::unique('mail_templates', 'code')->ignore($this->route('template')?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'reply_to' => ['nullable', 'string', 'max:255'],
            'bcc' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:200000'],
            'body_type' => ['required', 'in:html,text'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Код шаблона пишется заглавной латиницей, цифрами и подчёркиванием. Например: ORDER_CREATED',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'код',
            'name' => 'название',
            'from' => 'от кого',
            'to' => 'кому',
            'reply_to' => 'адрес для ответа',
            'subject' => 'тема',
            'body' => 'текст письма',
            'body_type' => 'формат письма',
        ];
    }
}
