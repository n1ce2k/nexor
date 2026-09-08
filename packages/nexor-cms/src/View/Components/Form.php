<?php

namespace Nexor\Cms\View\Components;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component as BaseComponent;
use Illuminate\View\View;
use RuntimeException;

/**
 * Форма обратной связи — аналог `main.feedback`.
 *
 * ```blade
 * <x-nexor::form />
 * <x-nexor::form :fields="['name', 'phone']" title="Заказать звонок" />
 * <x-nexor::form mail-template="feedback" to="sales@example.com" />
 * ```
 *
 * Отправляется на маршрут пакета, тот проверяет поля и шлёт письмо: по коду
 * почтового шаблона, если он задан, иначе простым списком «поле — значение».
 * Куда слать — из пропа `to`, иначе из настройки `contacts.email`.
 *
 * Инфоблоки здесь ни при чём, поэтому компонент наследуется напрямую от
 * Illuminate\View\Component — общий предок ему нужен только ради `template()`.
 */
class Form extends BaseComponent
{
    /** Поля, которые компонент умеет рисовать, и их правила. */
    public const FIELDS = [
        'name' => ['label' => 'Ваше имя', 'type' => 'text', 'rules' => 'required|string|max:150'],
        'email' => ['label' => 'E-mail', 'type' => 'email', 'rules' => 'required|email|max:190'],
        'phone' => ['label' => 'Телефон', 'type' => 'tel', 'rules' => 'nullable|string|max:50'],
        'company' => ['label' => 'Компания', 'type' => 'text', 'rules' => 'nullable|string|max:190'],
        'subject' => ['label' => 'Тема', 'type' => 'text', 'rules' => 'nullable|string|max:190'],
        'message' => ['label' => 'Сообщение', 'type' => 'textarea', 'rules' => 'required|string|max:5000'],
    ];

    /**
     * @param  string  $template  Имя шаблона вёрстки
     * @param  array<int, string>  $fields  Какие поля показывать
     * @param  string|null  $to  Кому слать; по умолчанию — настройка contacts.email
     * @param  string|null  $mailTemplate  Код почтового шаблона
     * @param  string|null  $title  Заголовок над формой
     * @param  string  $button  Подпись кнопки
     * @param  string  $success  Что сказать после отправки
     * @param  string|null  $consent  Текст согласия рядом с галочкой; null — без галочки
     * @param  string  $name  Имя формы, если их на странице несколько
     */
    public function __construct(
        public string $template = 'default',
        public array $fields = ['name', 'email', 'message'],
        public ?string $to = null,
        public ?string $mailTemplate = null,
        public ?string $title = null,
        public string $button = 'Отправить',
        public string $success = 'Спасибо! Мы получили ваше сообщение.',
        public ?string $consent = 'Согласен на обработку персональных данных',
        public string $name = 'feedback',
    ) {}

    public function render(): View
    {
        $view = 'nexor::components.form.'.$this->template;

        if (! view()->exists($view)) {
            throw new RuntimeException(
                "Шаблон «{$this->template}» компонента «form» не найден. Ожидался файл ".
                "resources/views/vendor/nexor/components/form/{$this->template}.blade.php."
            );
        }

        return view($view, [
            'formFields' => $this->resolveFields(),
            'formName' => $this->name,
            'formAction' => route('nexor.form'),
            // Настройки уезжают зашифрованными: иначе адрес получателя можно
            // было бы подменить в браузере и шлать письма куда угодно с чужого домена.
            'formConfig' => Crypt::encrypt([
                'name' => $this->name,
                'to' => $this->to,
                'template' => $this->mailTemplate,
                'fields' => $this->fields,
                'consent' => $this->consent !== null,
            ]),
            'sent' => session('nexor.form.sent') === $this->name,
            'sentMessage' => $this->success,
            // `@error` рассчитывает на $errors из middleware `web`. Форма может оказаться
            // и вне запроса — в письме, в консоли, в тесте, — и тогда его нет.
            'errors' => view()->shared('errors', new ViewErrorBag),
        ]);
    }

    /**
     * Описания выбранных полей.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function resolveFields(): array
    {
        $fields = [];

        foreach ($this->fields as $code) {
            if (! isset(self::FIELDS[$code])) {
                throw new RuntimeException(
                    "Компонент form не знает поля «{$code}». Доступны: ".implode(', ', array_keys(self::FIELDS)).'.'
                );
            }

            $fields[] = self::FIELDS[$code] + [
                'code' => $code,
                'required' => str_starts_with(self::FIELDS[$code]['rules'], 'required'),
                'value' => old($code, ''),
            ];
        }

        return $fields;
    }
}
