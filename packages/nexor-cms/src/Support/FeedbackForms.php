<?php

namespace Nexor\Cms\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nexor\Cms\Enums\FormFieldType;
use Nexor\Cms\Mail\FeedbackSubmissionMessage;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\FeedbackFormField;
use Nexor\Cms\Models\FeedbackSubmission;
use Nexor\Cms\Models\Setting;
use Throwable;

/**
 * Приём формы обратной связи: проверка, запись, письмо, Telegram.
 *
 * Одна логика и для обычной отправки (FormController), и для отправки без
 * перезагрузки (Livewire-компонент). Значения полей приходят под ключом
 * `fields.<код>`, согласие — `agreement`.
 */
class FeedbackForms
{
    /**
     * Закрытый диск для файлов из форм: скачать их можно только из админки.
     */
    public static function disk(): string
    {
        return (string) config('nexor.forms.disk', 'local');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(FeedbackForm $form): array
    {
        $rules = ['fields' => ['nullable', 'array']];

        foreach ($form->fields as $field) {
            $rules['fields.'.$field->code] = $field->rules();
        }

        if ($form->activeAgreement()) {
            $rules['agreement'] = ['accepted'];
        }

        return $rules;
    }

    /**
     * Названия полей для сообщений об ошибках.
     *
     * @return array<string, string>
     */
    public static function attributes(FeedbackForm $form): array
    {
        $attributes = ['agreement' => 'согласие'];

        foreach ($form->fields as $field) {
            $attributes['fields.'.$field->code] = mb_strtolower($field->label);
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'agreement.accepted' => 'Нужно согласие, чтобы отправить форму.',
            'fields.*.regex' => 'Проверьте номер: цифры, пробелы, скобки, «+» и «-».',
        ];
    }

    /**
     * Сохраняет запись и шлёт письмо. Значения — уже проверенные.
     *
     * @param  array<string, mixed>  $values  Код поля → строка или файл
     * @param  array{page_url?: string|null, ip?: string|null, user_agent?: string|null, user_id?: int|null}  $context
     */
    public static function submit(FeedbackForm $form, array $values, array $context = []): ?FeedbackSubmission
    {
        $data = [];

        foreach ($form->fields->values() as $position => $field) {
            $data[$field->code] = [
                'label' => $field->label,
                'type' => $field->type->value,
                'value' => self::value($form, $field, $values[$field->code] ?? null),
                'sort' => $position,
            ];
        }

        $agreement = $form->activeAgreement();

        $submission = new FeedbackSubmission([
            'form_id' => $form->id,
            'data' => $data,
            'agreement_id' => $agreement?->id,
            'agreed_at' => $agreement ? now() : null,
            'page_url' => isset($context['page_url']) ? Str::limit((string) $context['page_url'], 1000, '') : null,
            'ip' => $context['ip'] ?? null,
            'user_agent' => isset($context['user_agent']) ? Str::limit((string) $context['user_agent'], 500, '') : null,
            'user_id' => $context['user_id'] ?? null,
        ]);

        if ($form->store_submissions) {
            $submission->save();
        }

        self::mail($form, $submission);
        FormTelegram::notify($form, $submission);

        // Запись не нужна — файлы писались только ради вложений в письмо.
        if (! $form->store_submissions) {
            foreach ($submission->files() as $file) {
                Storage::disk(self::disk())->delete($file['path']);
            }

            return null;
        }

        return $submission;
    }

    /**
     * Подстановки письма: `#<КОД ПОЛЯ>#` и служебные.
     *
     * @return array<string, string>
     */
    public static function placeholders(FeedbackForm $form, ?FeedbackSubmission $submission = null): array
    {
        $values = [];
        $lines = [];

        foreach ($submission?->fields() ?? [] as $code => $field) {
            $value = $field['value'] ?? '';
            $text = is_array($value) ? (string) ($value['name'] ?? '') : (string) $value;

            $values[strtoupper($code)] = $text;

            if ($text !== '') {
                $lines[] = $field['label'].': '.$text;
            }
        }

        return $values + [
            'FORM_NAME' => $form->name,
            'FORM_ID' => (string) $form->id,
            'FORM_CODE' => $form->code,
            'SUBMISSION_ID' => (string) ($submission?->id ?? ''),
            'SUBMISSION_URL' => url(Nexor::panelBase().'/forms/'.$form->id.'/edit?tab=submissions'),
            'PAGE_URL' => (string) ($submission?->page_url ?? ''),
            'DATE' => now()->format('d.m.Y H:i'),
            'ALL_FIELDS' => implode("\n", $lines),
        ];
    }

    /**
     * Подсказка редактору: какие подстановки есть у формы.
     *
     * @return array<int, string>
     */
    public static function placeholderNames(FeedbackForm $form): array
    {
        return [
            ...$form->fields->map(fn (FeedbackFormField $field) => strtoupper($field->code))->all(),
            'FORM_NAME', 'FORM_ID', 'FORM_CODE', 'SUBMISSION_ID', 'SUBMISSION_URL', 'PAGE_URL', 'DATE', 'ALL_FIELDS',
        ];
    }

    protected static function value(FeedbackForm $form, FeedbackFormField $field, mixed $value): mixed
    {
        if ($field->type !== FormFieldType::File) {
            return is_scalar($value) ? trim((string) $value) : '';
        }

        if (! $value instanceof UploadedFile) {
            return null;
        }

        $extension = strtolower($value->guessExtension() ?: $value->getClientOriginalExtension() ?: 'bin');
        $path = $value->storeAs('feedback/'.$form->id, Str::uuid().'.'.$extension, self::disk());

        return [
            'path' => $path,
            'name' => Str::limit(basename($value->getClientOriginalName()), 200, ''),
            'size' => (int) $value->getSize(),
        ];
    }

    protected static function mail(FeedbackForm $form, FeedbackSubmission $submission): void
    {
        $template = $form->mailTemplate?->is_active ? $form->mailTemplate : null;
        $data = self::placeholders($form, $submission);

        $to = self::addresses($template ? $template->render('to', $data) : '')
            ?: self::addresses((string) $form->to)
            ?: self::addresses((string) Setting::get('contacts.email'));

        if ($to === []) {
            Log::warning("Форма «{$form->name}» отправлена, но получатель не задан: заполните «Кому» у формы или в почтовом шаблоне.");

            return;
        }

        $subject = $template?->render('subject', $data) ?: 'Форма «'.$form->name.'» на сайте';
        // В HTML-письмо значения посетителя идут экранированными: иначе через поле
        // формы можно было бы подменить разметку письма.
        $body = match (true) {
            $template?->isHtml() => $template->render('body', array_map(fn (string $value) => nl2br(e($value)), $data)),
            $template !== null => $template->render('body', $data),
            default => $data['ALL_FIELDS'],
        };

        // Ответить посетителю прямо из почты — на первый заполненный e-mail.
        $replyTo = collect($submission->fields())
            ->first(fn (array $field) => $field['type'] === FormFieldType::Email->value && filled($field['value']))['value'] ?? null;

        try {
            MailConfig::apply();

            Mail::to($to)->send(new FeedbackSubmissionMessage(
                subjectLine: $subject,
                body: $body,
                isHtml: (bool) $template?->isHtml(),
                files: array_values($submission->files()),
                disk: self::disk(),
                fromAddress: $template?->from ? $template->render('from', $data) : null,
                replyToAddress: $template?->reply_to ? $template->render('reply_to', $data) : $replyTo,
                bccAddresses: $template?->bcc ? self::addresses($template->render('bcc', $data)) : [],
            ));
        } catch (Throwable $exception) {
            // Письмо не ушло — запись уже сохранена, заявка не потеряется.
            Log::error("Письмо формы «{$form->name}» не отправлено: {$exception->getMessage()}");
        }
    }

    /**
     * @return array<int, string>
     */
    protected static function addresses(string $list): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/[,;]+/', $list) ?: []),
            fn (string $address) => filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }
}
