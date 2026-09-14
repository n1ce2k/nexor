<?php

namespace Nexor\Shop\Enums;

/**
 * Тип поля формы заказа.
 */
enum OrderFieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Phone = 'phone';
    case Textarea = 'textarea';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Строка',
            self::Email => 'E-mail',
            self::Phone => 'Телефон',
            self::Textarea => 'Многострочный текст',
        };
    }

    /**
     * Правила проверки значения, кроме обязательности.
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::Text => ['string', 'max:255'],
            self::Email => ['string', 'email', 'max:255'],
            self::Phone => ['string', 'max:40', 'regex:/^[0-9+()\-\s]{5,40}$/'],
            self::Textarea => ['string', 'max:5000'],
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
