<?php

namespace Nexor\Cms\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Nexor\Cms\Models\FeedbackForm as Form;
use Nexor\Cms\Support\FeedbackForms;
use Nexor\Cms\Support\FormCaptcha;
use Nexor\Cms\View\Components\Form as FormComponent;

/**
 * Форма обратной связи без перезагрузки страницы.
 *
 * Обычно подключается сама — компонентом `<x-nexor::form :id="3" />`, если у
 * формы включено «Отправка без перезагрузки». Вёрстка — шаблон компонента
 * `form` с суффиксом `-livewire`: `components/form/default-livewire.blade.php`.
 */
class FeedbackForm extends Component
{
    use WithFileUploads;

    /** Сколько отправок в минуту разрешено с одного адреса. */
    public const PER_MINUTE = 10;

    /** Настройки с #[Locked] задаёт шаблон сайта — из браузера их не поменять. */
    #[Locked]
    public int $formId;

    #[Locked]
    public string $template = 'default';

    #[Locked]
    public ?string $title = null;

    #[Locked]
    public ?string $button = null;

    #[Locked]
    public ?string $success = null;

    #[Locked]
    public ?string $pageUrl = null;

    /** @var array<string, mixed> Значения полей: код → строка или файл */
    public array $fields = [];

    public bool $agreement = false;

    /** Ловушка для ботов: людям поле не видно. */
    public string $website = '';

    public bool $sent = false;

    /** Токен Yandex SmartCaptcha или Google reCAPTCHA — кладёт скрипт капчи. */
    public string $captchaToken = '';

    /** Задача Nexor Captcha: выдаёт сервер, из браузера не поменять. */
    #[Locked]
    public ?string $captchaId = null;

    public string $captchaAnswer = '';

    /** Форма на время одного запроса — между запросами не хранится. */
    protected ?Form $loadedForm = null;

    public function mount(): void
    {
        // Адрес страницы, с которой отправили, — для записи; запрос Livewire
        // потом приходит уже на служебный адрес.
        $this->pageUrl ??= request()->fullUrl();

        foreach ($this->form()->fields as $field) {
            $this->fields[$field->code] ??= $field->type->value === 'file' ? null : '';
        }

        $this->captchaId = FormCaptcha::challenge($this->form());
    }

    /**
     * Кнопка «другой код» у Nexor Captcha.
     */
    public function refreshCaptcha(): void
    {
        $this->captchaId = FormCaptcha::challenge($this->form());
        $this->captchaAnswer = '';
    }

    public function submit(): void
    {
        $form = $this->form();

        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        $key = 'nexor-form:'.$form->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            throw ValidationException::withMessages([
                'form' => 'Слишком много отправок — попробуйте через минуту.',
            ]);
        }

        RateLimiter::hit($key);

        $this->validate(
            FeedbackForms::rules($form),
            FeedbackForms::messages(),
            FeedbackForms::attributes($form),
        );

        $captchaError = FormCaptcha::verify($form, [
            'token' => $this->captchaToken,
            'id' => $this->captchaId,
            'answer' => $this->captchaAnswer,
        ], request()->ip());

        if ($captchaError !== null) {
            // Ответ капчи одноразовый: виджету — сброс, Nexor Captcha — новый код.
            $this->captchaToken = '';
            $this->refreshCaptcha();
            $this->dispatch('nexor-captcha-reset', form: $this->getId());
            $this->addError(FormCaptcha::ERROR_KEY, $captchaError);

            return;
        }

        FeedbackForms::submit($form, $this->fields, [
            'page_url' => $this->pageUrl,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
        ]);

        $this->sent = true;
        $this->fields = [];
        $this->agreement = false;
    }

    public function render(): View
    {
        $form = $this->form();

        return view(FormComponent::templateView($this->template.'-livewire'), [
            ...FormComponent::entityData($form, 'form-'.$form->id.'-'.$this->getId(), $this->captchaId),
            'formTitle' => $this->title ?? $form->title,
            'formButton' => $this->button ?? $form->button_text,
            'sentMessage' => $this->success ?? $form->success_text,
        ]);
    }

    protected function form(): Form
    {
        return $this->loadedForm ??= Form::findForSite($this->formId)
            ?? abort(404, 'Форма отключена или удалена.');
    }
}
