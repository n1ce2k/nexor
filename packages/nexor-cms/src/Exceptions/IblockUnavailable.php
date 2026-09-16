<?php

namespace Nexor\Cms\Exceptions;

use RuntimeException;

/**
 * Инфоблока с таким кодом или id нет, или он отключён.
 *
 * Компоненты сайта ловят это и выводят заглушку «Инфоблок недоступен»,
 * чтобы отключённый в админке инфоблок не ронял всю страницу.
 */
class IblockUnavailable extends RuntimeException
{
    public function __construct(public readonly string $iblock)
    {
        parent::__construct("Инфоблок «{$iblock}» не найден или отключён.");
    }
}
