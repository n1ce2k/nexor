<?php

namespace Nexor\Cms\Enums;

/**
 * Тип поля формы обратной связи.
 */
enum FormFieldType: string
{
    case String = 'string';
    case Textarea = 'textarea';
    case Phone = 'phone';
    case Email = 'email';
    case File = 'file';

    /** Файл по умолчанию: документы и картинки, до 10 МБ. */
    public const FILE_EXTENSIONS = 'pdf,doc,docx,xls,xlsx,odt,ods,rtf,txt,jpg,jpeg,png,webp,zip';

    public const FILE_MAX_KB = 10240;

    public function label(): string
    {
        return match ($this) {
            self::String => 'Строка',
            self::Textarea => 'Текст (многострочный)',
            self::Phone => 'Телефон',
            self::Email => 'E-mail',
            self::File => 'Файл',
        };
    }

    /** Атрибут `type` у `<input>`; у многострочного — textarea. */
    public function inputType(): string
    {
        return match ($this) {
            self::String => 'text',
            self::Textarea => 'textarea',
            self::Phone => 'tel',
            self::Email => 'email',
            self::File => 'file',
        };
    }

    /**
     * Правила проверки значения.
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    public function rules(bool $required, array $settings = []): array
    {
        $rules = [$required ? 'required' : 'nullable'];

        return [...$rules, ...match ($this) {
            self::String => ['string', 'max:255'],
            self::Textarea => ['string', 'max:10000'],
            // Цифры, пробелы, скобки, плюс и дефис; от 6 до 30 символов.
            self::Phone => ['string', 'max:30', 'regex:/^\+?[0-9\s\-()]{6,30}$/'],
            self::Email => ['string', 'email', 'max:190'],
            self::File => [
                'file',
                'mimes:'.self::extensions($settings),
                'max:'.(int) ($settings['max_kb'] ?? self::FILE_MAX_KB),
            ],
        }];
    }

    /**
     * Разрешённые расширения файла: из настроек поля или по умолчанию.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function extensions(array $settings): string
    {
        $list = preg_split('/[\s,;]+/', strtolower((string) ($settings['extensions'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);

        // HTML и скрипты не принимаются, даже если их вписали в настройки.
        $list = array_diff($list ?: [], ['html', 'htm', 'svg', 'php', 'phtml', 'js', 'exe', 'sh', 'bat']);

        return $list === [] ? self::FILE_EXTENSIONS : implode(',', $list);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
