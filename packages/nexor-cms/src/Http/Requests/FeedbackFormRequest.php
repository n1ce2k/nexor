<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Nexor\Cms\Enums\FormFieldType;
use Nexor\Cms\Support\Captcha\NexorCaptcha;
use Nexor\Cms\Support\FormCaptcha;
use Nexor\Cms\Support\Secrets;

/**
 * Форма вместе с полями: поля приходят списком и сохраняются целиком.
 */
class FeedbackFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->route('form') ? 'forms.update' : 'forms.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/',
                // Код из одних цифр неотличим от id: <x-nexor::form form="5" />.
                'not_regex:/^[0-9]+$/',
                Rule::unique('feedback_forms', 'code')->ignore($this->route('form')?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'button_text' => ['required', 'string', 'max:100'],
            'success_text' => ['required', 'string', 'max:1000'],
            'mail_template_id' => ['nullable', 'integer', Rule::exists('mail_templates', 'id')],
            'to' => ['nullable', 'string', 'max:255'],
            'agreement_id' => ['nullable', 'integer', Rule::exists('agreements', 'id')],
            'agreement_popup' => ['boolean'],
            'ajax' => ['boolean'],
            'store_submissions' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],

            'fields' => ['present', 'array', 'max:50'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.code' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::enum(FormFieldType::class)],
            'fields.*.is_required' => ['boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.settings' => ['nullable', 'array'],
            'fields.*.settings.extensions' => ['nullable', 'string', 'max:255'],
            'fields.*.settings.max_kb' => ['nullable', 'integer', 'min:1', 'max:51200'],
            'fields.*.settings.rows' => ['nullable', 'integer', 'min:2', 'max:30'],

            'telegram' => ['nullable', 'array'],
            'telegram.enabled' => ['boolean'],
            'telegram.token' => ['nullable', 'string', 'max:100'],
            'telegram.chat_ids' => ['nullable', 'required_if_accepted:telegram.enabled', 'string', 'max:500'],
            'telegram.thread_id' => ['nullable', 'integer', 'min:1'],
            'telegram.message' => ['nullable', 'string', 'max:3500'],
            'telegram.send_files' => ['boolean'],
            'telegram.silent' => ['boolean'],

            'protection' => ['nullable', 'array'],
            'protection.captcha' => ['nullable', Rule::in([FormCaptcha::NONE, ...array_keys(FormCaptcha::drivers())])],
            'protection.yandex.client_key' => ['nullable', 'required_if:protection.captcha,yandex', 'string', 'max:255'],
            'protection.yandex.server_key' => ['nullable', 'string', 'max:255'],
            'protection.yandex.webview' => ['boolean'],
            'protection.yandex.invisible' => ['boolean'],
            'protection.yandex.hide_shield' => ['boolean'],
            'protection.google.site_key' => ['nullable', 'required_if:protection.captcha,google', 'string', 'max:255'],
            'protection.google.secret_key' => ['nullable', 'string', 'max:255'],
            'protection.google.version' => ['nullable', 'in:v2,v3'],
            'protection.google.min_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'protection.nexor.length' => ['nullable', 'integer', 'min:4', 'max:8'],
            'protection.nexor.chars' => ['nullable', Rule::in(array_keys(NexorCaptcha::CHARSETS))],
        ];
    }

    /**
     * Секреты: пустое поле допустимо, только если секрет уже сохранён.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $form = $this->route('form');

                if ($this->boolean('telegram.enabled') && ! Secrets::resolve($this->input('telegram.token'), $form?->telegram['token'] ?? null)) {
                    $validator->errors()->add('telegram.token', 'Укажите токен бота.');
                }

                $secrets = ['yandex' => 'server_key', 'google' => 'secret_key'];
                $captcha = $this->input('protection.captcha');

                if (isset($secrets[$captcha])) {
                    $key = $secrets[$captcha];

                    if (! Secrets::resolve($this->input("protection.{$captcha}.{$key}"), $form?->protection[$captcha][$key] ?? null)) {
                        $validator->errors()->add("protection.{$captcha}.{$key}", $captcha === 'yandex' ? 'Укажите ключ сервера.' : 'Укажите секретный ключ.');
                    }
                }
            },
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
            'button_text' => 'текст кнопки',
            'success_text' => 'сообщение после отправки',
            'to' => 'кому',
            'fields.*.code' => 'код поля',
            'fields.*.label' => 'название поля',
            'fields.*.type' => 'тип поля',
            'fields.*.settings.max_kb' => 'размер файла',
            'telegram.chat_ids' => 'chat id',
            'telegram.thread_id' => 'id темы',
            'telegram.message' => 'текст сообщения',
            'protection.yandex.client_key' => 'ключ клиента',
            'protection.google.site_key' => 'ключ сайта',
            'protection.google.min_score' => 'минимальный балл',
            'protection.nexor.length' => 'длина кода',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Символьный код — латиница в нижнем регистре, цифры, дефис и подчёркивание.',
            'code.not_regex' => 'Символьный код не может состоять из одних цифр — так пишется id формы.',
            'fields.*.code.regex' => 'Код поля — латиница, цифры и подчёркивание, начинается с буквы: он же подстановка #КОД# в письме.',
            'fields.*.code.distinct' => 'Коды полей не должны повторяться.',
            'telegram.chat_ids.required_if_accepted' => 'Укажите chat id, куда отправлять заявки.',
            'protection.yandex.client_key.required_if' => 'Укажите ключ клиента.',
            'protection.google.site_key.required_if' => 'Укажите ключ сайта.',
        ];
    }
}
