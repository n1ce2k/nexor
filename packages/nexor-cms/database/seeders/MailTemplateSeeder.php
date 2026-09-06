<?php

namespace Nexor\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use Nexor\Cms\Models\MailTemplate;

class MailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'FEEDBACK_FORM',
                'name' => 'Заявка с сайта',
                'description' => 'Уходит администратору, когда посетитель отправил форму обратной связи.',
                'from' => '#SITE_EMAIL#',
                'to' => '#SITE_EMAIL#',
                'subject' => '#SITE_NAME#: новая заявка от #NAME#',
                'body' => "<p>Поступила новая заявка с сайта <b>#SITE_NAME#</b>.</p>\n<p><b>Имя:</b> #NAME#<br>\n<b>Телефон:</b> #PHONE#<br>\n<b>E-mail:</b> #EMAIL#</p>\n<p><b>Сообщение:</b><br>#MESSAGE#</p>",
                'body_type' => 'html',
                'sort' => 100,
            ],
            [
                'code' => 'USER_WELCOME',
                'name' => 'Приветствие нового пользователя',
                'description' => 'Отправляется при создании учётной записи.',
                'from' => '#SITE_EMAIL#',
                'to' => '#EMAIL#',
                'subject' => 'Добро пожаловать в #SITE_NAME#',
                'body' => "<p>Здравствуйте, #NAME#!</p>\n<p>Для вас создана учётная запись на сайте #SITE_NAME#.</p>\n<p>Логин: #EMAIL#</p>",
                'body_type' => 'html',
                'sort' => 200,
            ],
        ];

        foreach ($templates as $template) {
            MailTemplate::query()->updateOrCreate(['code' => $template['code']], $template);
        }
    }
}
