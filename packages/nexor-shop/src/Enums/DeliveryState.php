<?php

namespace Nexor\Shop\Enums;

/**
 * Где заказ в передаче службе доставки.
 *
 * `Pending` — заявка ушла, но трек-номер служба ещё не присвоила: СДЭК делает
 * это не в том же ответе, а спустя несколько секунд.
 */
enum DeliveryState: string
{
    case None = 'none';
    case Pending = 'pending';
    case Registered = 'registered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Не передан',
            self::Pending => 'Ждём номер',
            self::Registered => 'Передан',
            self::Failed => 'Ошибка',
        };
    }

    /**
     * Цвет отметки в панели.
     */
    public function color(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Pending => 'amber',
            self::Registered => 'green',
            self::Failed => 'red',
        };
    }
}
