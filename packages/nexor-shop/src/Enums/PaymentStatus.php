<?php

namespace Nexor\Shop\Enums;

/**
 * Состояние платежа — теми же словами, что у ЮKassa.
 *
 * `WaitingForCapture` появляется только в двухстадийной схеме; мы списываем
 * сразу, но состояние оставлено: провайдер может его прислать.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case WaitingForCapture = 'waiting_for_capture';
    case Succeeded = 'succeeded';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ожидает оплаты',
            self::WaitingForCapture => 'Ожидает подтверждения',
            self::Succeeded => 'Оплачен',
            self::Canceled => 'Отменён',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::WaitingForCapture => 'amber',
            self::Succeeded => 'green',
            self::Canceled => 'red',
        };
    }

    /**
     * Платёж закончен — ходить за его состоянием больше незачем.
     */
    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Canceled;
    }
}
