<?php

namespace Nexor\Cms\View\Components;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component as BaseComponent;
use Illuminate\View\View;
use Nexor\Cms\Enums\FormFieldType;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Support\FormCaptcha;
use RuntimeException;

/**
 * Форма обратной связи — аналог `main.feedback` и веб-форм Битрикса.
 *
 * ```blade
 * <x-nexor::form :id="3" />                      форма из админки («Формы ОС») по id
 * <x-nexor::form form="callback" />             …или по символьному коду
 * <x-nexor::form :fields="['name', 'phone']" title="Заказать звонок" />   простая форма без админки
 * ```
 *
 * Форма из админки берёт поля, почтовый шаблон, соглашение и запись заявок из
 * своих настроек. Включено «Отправка без перезагрузки» — внутри работает
 * Livewire-компонент `nexor::feedback-form`, иначе обычная отправка на маршрут
 * пакета. Отключённая или удалённая форма не выводится.
 *
 * Простая форма (без `id`/`form`) — прежний режим: поля из фиксированного
 * набора, письмо по коду почтового шаблона или списком «поле — значение».
 *
 * Инфоблоки здесь ни при чём, поэтому компонент наследуется напрямую от
 * Illuminate\View\Component.
 */
class Form extends BaseComponent
{
    /** Поля простой формы, которые компонент умеет рисовать, и их правила. */
    public const FIELDS = [
        'name' => ['label' => 'Ваше имя', 'type' => 'text', 'rules' => 'required|string|max:150'],
        'email' => ['label' => 'E-mail', 'type' => 'email', 'rules' => 'required|email|max:190'],
        'phone' => ['label' => 'Телефон', 'type' => 'tel', 'rules' => 'nullable|string|max:50'],
        'company' => ['label' => 'Компания', 'type' => 'text', 'rules' => 'nullable|string|max:190'],
        'subject' => ['label' => 'Тема', 'type' => 'text', 'rules' => 'nullable|string|max:190'],
        'message' => ['label' => 'Сообщение', 'type' => 'textarea', 'rules' => 'required|string|max:5000'],
    ];

    protected const DEFAULT_BUTTON = 'Отправить';

    protected const DEFAULT_SUCCESS = 'Спасибо! Мы получили ваше сообщение.';

    protected ?FeedbackForm $entity = null;

    /**
     * @param  string  $template  Имя шаблона вёрстки
     * @param  array<int, string>  $fields  Какие поля показывать (простая форма)
     * @param  string|null  $to  Кому слать; по умолчанию — настройка contacts.email (простая форма)
     * @param  string|null  $mailTemplate  Код почтового шаблона (простая форма)
     * @param  string|null  $title  Заголовок над формой; у формы из админки — вместо её заголовка
     * @param  string  $button  Подпись кнопки; у формы из админки — вместо её подписи, если задана
     * @param  string  $success  Что сказать после отправки; у формы из админки — аналогично
     * @param  string|null  $consent  Текст согласия рядом с галочкой; null — без галочки (простая форма)
     * @param  string  $name  Имя формы, если их на странице несколько
     * @param  int|string|null  $id  Id формы из админки
     * @param  string|null  $form  Символьный код формы из админки
     */
    public function __construct(
        public string $template = 'default',
        public array $fields = ['name', 'email', 'message'],
        public ?string $to = null,
        public ?string $mailTemplate = null,
        public ?string $title = null,
        public string $button = self::DEFAULT_BUTTON,
        public string $success = self::DEFAULT_SUCCESS,
        public ?string $consent = 'Согласен на обработку персональных данных',
        public string $name = 'feedback',
        public int|string|null $id = null,
        public ?string $form = null,
    ) {}

    /**
     * Форма из админки не выводится, если её отключили или удалили.
     */
    public function shouldRender(): bool
    {
        if (! $this->usesEntity()) {
            return true;
        }

        $this->entity = FeedbackForm::findForSite($this->id ?? $this->form);

        return $this->entity !== null;
    }

    public function render(): View
    {
        if ($this->entity) {
            return $this->renderEntity($this->entity);
        }

        return view(self::templateView($this->template), [
            'formFields' => $this->legacyFields(),
            'formAgreement' => $this->consent === null ? null : [
                'name' => 'consent',
                'parts' => ['before' => $this->consent, 'link' => '', 'after' => ''],
                'popup' => false,
                'url' => null,
                'model' => null,
                'modalId' => $this->name.'-agreement',
                'checked' => (bool) old('consent'),
            ],
            'formCaptcha' => null,
            'formName' => $this->name,
            'formTitle' => $this->title,
            'formButton' => $this->button,
            'formAction' => route('nexor.form'),
            'multipart' => false,
            // Настройки уезжают зашифрованными: иначе адрес получателя можно
            // было бы подменить в браузере и слать письма куда угодно с чужого домена.
            'formConfig' => Crypt::encrypt([
                'name' => $this->name,
                'to' => $this->to,
                'template' => $this->mailTemplate,
                'fields' => $this->fields,
                'consent' => $this->consent !== null,
            ]),
            'sent' => session('nexor.form.sent') === $this->name,
            'sentMessage' => $this->success,
            'errors' => self::errors(),
        ]);
    }

    /**
     * Вьюха шаблона компонента; нет файла — понятная ошибка.
     */
    public static function templateView(string $template): string
    {
        $view = 'nexor::components.form.'.$template;

        if (! view()->exists($view)) {
            throw new RuntimeException(
                "Шаблон «{$template}» компонента «form» не найден. Ожидался файл ".
                "resources/views/vendor/nexor/components/form/{$template}.blade.php."
            );
        }

        return $view;
    }

    /**
     * Поля и соглашение формы из админки в виде, который понимают шаблоны.
     *
     * @return array{formFields: array<int, array<string, mixed>>, formAgreement: array<string, mixed>|null, formCaptcha: array<string, mixed>|null, multipart: bool, idPrefix: string}
     */
    public static function entityData(FeedbackForm $form, string $idPrefix, ?string $captchaChallenge = null): array
    {
        $fields = [];

        foreach ($form->fields as $field) {
            $fields[] = [
                'code' => $field->code,
                'label' => $field->label,
                'type' => $field->type->inputType(),
                'required' => $field->is_required,
                'placeholder' => $field->placeholder,
                'accept' => $field->accept(),
                'rows' => (int) ($field->settings['rows'] ?? 5),
                'value' => $field->type === FormFieldType::File ? null : old('fields.'.$field->code, ''),
                'inputName' => 'fields['.$field->code.']',
                'errorKey' => 'fields.'.$field->code,
                'wireModel' => 'fields.'.$field->code,
            ];
        }

        $agreement = $form->activeAgreement();

        return [
            'formFields' => $fields,
            // Не `agreement`: так называется свойство Livewire-компонента (галочка),
            // и Livewire подставил бы его во вьюху поверх этого массива.
            'formAgreement' => $agreement ? [
                'name' => 'agreement',
                'parts' => $agreement->labelParts(),
                'popup' => $form->agreement_popup,
                'url' => $agreement->url(),
                'model' => $agreement,
                'modalId' => $idPrefix.'-agreement',
                'checked' => (bool) old('agreement'),
            ] : null,
            // Nexor Captcha без готовой задачи получает новую — так у обычной формы.
            'formCaptcha' => FormCaptcha::viewData($form, $captchaChallenge),
            'multipart' => $form->fields->contains(fn ($field) => $field->type === FormFieldType::File),
            'idPrefix' => $idPrefix,
        ];
    }

    protected function renderEntity(FeedbackForm $form): View
    {
        if ($form->ajax) {
            return view('nexor::components.form.partials.livewire', [
                'livewireParams' => [
                    'formId' => $form->id,
                    'template' => $this->template,
                    'title' => $this->title,
                    'button' => $this->overriddenButton(),
                    'success' => $this->overriddenSuccess(),
                ],
                'livewireKey' => 'nexor-form-'.$form->id.'-'.$this->name,
            ]);
        }

        return view(self::templateView($this->template), [
            ...self::entityData($form, $this->name.'-'.$form->id),
            'formName' => $this->name,
            'formTitle' => $this->title ?? $form->title,
            'formButton' => $this->overriddenButton() ?? $form->button_text,
            'formAction' => route('nexor.form'),
            'formConfig' => Crypt::encrypt(['name' => $this->name, 'form_id' => $form->id]),
            'sent' => session('nexor.form.sent') === $this->name,
            'sentMessage' => $this->overriddenSuccess() ?? $form->success_text,
            'errors' => self::errors(),
        ]);
    }

    /** Подпись кнопки, если её задали в теге, а не оставили по умолчанию. */
    protected function overriddenButton(): ?string
    {
        return $this->button !== self::DEFAULT_BUTTON ? $this->button : null;
    }

    protected function overriddenSuccess(): ?string
    {
        return $this->success !== self::DEFAULT_SUCCESS ? $this->success : null;
    }

    protected function usesEntity(): bool
    {
        return ($this->id !== null && $this->id !== '') || ($this->form !== null && $this->form !== '');
    }

    /**
     * `@error` рассчитывает на $errors из middleware `web`. Форма может оказаться
     * и вне запроса — в письме, в консоли, в тесте, — и тогда его нет.
     */
    protected static function errors(): ViewErrorBag
    {
        return view()->shared('errors', new ViewErrorBag);
    }

    /**
     * Поля простой формы.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function legacyFields(): array
    {
        $fields = [];

        foreach ($this->fields as $code) {
            if (! isset(self::FIELDS[$code])) {
                throw new RuntimeException(
                    "Компонент form не знает поля «{$code}». Доступны: ".implode(', ', array_keys(self::FIELDS)).'.'
                );
            }

            $fields[] = [
                'code' => $code,
                'label' => self::FIELDS[$code]['label'],
                'type' => self::FIELDS[$code]['type'],
                'required' => str_starts_with(self::FIELDS[$code]['rules'], 'required'),
                'placeholder' => null,
                'accept' => null,
                'rows' => 5,
                'value' => old($code, ''),
                'inputName' => $code,
                'errorKey' => $code,
                'wireModel' => null,
            ];
        }

        return $fields;
    }
}
