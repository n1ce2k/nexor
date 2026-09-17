<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Nexor\Cms\Mail\FormMessage;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\MailTemplate;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\FeedbackForms;
use Nexor\Cms\Support\FormCaptcha;
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
    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $config = $this->config($request);

        // Honeypot: поле спрятано от людей, боты его заполняют.
        if (filled($request->input('website'))) {
            return $this->done($request, $config['name']);
        }

        // Форма из админки («Формы ОС»): поля, письмо и запись — из её настроек.
        if (isset($config['form_id'])) {
            return $this->submitEntity($request, $config);
        }

        $data = $request->validate($this->rules($config));

        unset($data['consent']);

        $this->send($config, $data);

        return back()
            ->with('nexor.form.sent', $config['name'])
            ->with('status', 'Сообщение отправлено.');
    }

    /**
     * @param  array{name: string, form_id: int}  $config
     */
    protected function submitEntity(Request $request, array $config): RedirectResponse|JsonResponse
    {
        $form = FeedbackForm::findForSite($config['form_id'])
            ?? abort(404, 'Форма отключена или удалена.');

        $data = $request->validate(
            FeedbackForms::rules($form),
            FeedbackForms::messages(),
            FeedbackForms::attributes($form),
        );

        // Капча — после полей: ошибка в поле не должна сжигать пройденную проверку.
        $captchaError = FormCaptcha::verify($form, [
            'token' => $request->input('captcha_token'),
            'id' => $request->input('captcha_id'),
            'answer' => $request->input('captcha_answer'),
        ], $request->ip());

        if ($captchaError !== null) {
            throw ValidationException::withMessages([FormCaptcha::ERROR_KEY => $captchaError]);
        }

        $submission = FeedbackForms::submit($form, $data['fields'] ?? [], [
            'page_url' => url()->previous(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        return $this->done($request, $config['name'], $form->success_text, $submission?->id);
    }

    protected function done(Request $request, string $name, ?string $message = null, ?int $submissionId = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message ?? 'Сообщение отправлено.', 'id' => $submissionId]);
        }

        return back()
            ->with('nexor.form.sent', $name)
            ->with('status', 'Сообщение отправлено.');
    }

    /**
     * @return array{name: string, to?: string|null, template?: string|null, fields?: array<int, string>, consent?: bool, form_id?: int}
     */
    protected function config(Request $request): array
    {
        try {
            $config = Crypt::decrypt((string) $request->input('_form'));
        } catch (DecryptException) {
            abort(422, 'Форма повреждена — обновите страницу.');
        }

        abort_unless(is_array($config) && (isset($config['fields']) || isset($config['form_id'])), 422);

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
