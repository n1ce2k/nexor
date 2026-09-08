<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Nexor\Cms\Mail\FormMessage;
use Nexor\Cms\Models\MailTemplate;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\View\Components\Form;

/**
 * Приём форм обратной связи компонента `form`.
 *
 * Адрес получателя, код почтового шаблона и список полей приходят одним
 * зашифрованным полем, которое положил сам компонент. Иначе форму можно было бы
 * переписать в браузере и слать письма куда угодно с чужого домена — открытый
 * релей. Расшифровка проверяет подпись, поэтому подменённая настройка просто не
 * пройдёт, а правила валидации строятся из неё, а не из запроса.
 */
class FormController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $config = $this->config($request);

        // Honeypot: поле спрятано от людей, боты его заполняют.
        if (filled($request->input('website'))) {
            return back()->with('nexor.form.sent', $config['name']);
        }

        $data = $request->validate($this->rules($config));

        unset($data['consent']);

        $this->send($config, $data);

        return back()
            ->with('nexor.form.sent', $config['name'])
            ->with('status', 'Сообщение отправлено.');
    }

    /**
     * @return array{name: string, to: string|null, template: string|null, fields: array<int, string>, consent: bool}
     */
    protected function config(Request $request): array
    {
        try {
            $config = Crypt::decrypt((string) $request->input('_form'));
        } catch (DecryptException) {
            abort(422, 'Форма повреждена — обновите страницу.');
        }

        abort_unless(is_array($config) && isset($config['fields']), 422);

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    protected function rules(array $config): array
    {
        $rules = [];

        foreach ($config['fields'] as $code) {
            if (isset(Form::FIELDS[$code])) {
                $rules[$code] = Form::FIELDS[$code]['rules'];
            }
        }

        if ($config['consent'] ?? false) {
            $rules['consent'] = 'accepted';
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    protected function send(array $config, array $data): void
    {
        $to = $config['to'] ?: Setting::get('contacts.email');

        if (! $to) {
            // Некому слать — молчать нельзя, иначе заявки уходят в никуда.
            Log::warning('Форма отправлена, но получатель не задан: заполните настройку contacts.email.', $data);

            return;
        }

        $template = $config['template']
            ? MailTemplate::query()->where('code', $config['template'])->active()->first()
            : null;

        $subject = $template?->render('subject', $data) ?: 'Сообщение с сайта';
        $body = $template
            ? $template->render('body', $data)
            : $this->plainBody($data);

        Mail::to($to)->send(new FormMessage(
            subjectLine: $subject,
            body: $body,
            isHtml: (bool) $template?->isHtml(),
            data: $data,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function plainBody(array $data): string
    {
        $lines = [];

        foreach ($data as $code => $value) {
            $label = Form::FIELDS[$code]['label'] ?? $code;
            $lines[] = $label.': '.$value;
        }

        return implode("\n", $lines);
    }
}
