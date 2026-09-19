<?php

namespace Nexor\Shop\Support\Payments;

use RuntimeException;

/**
 * Провайдер отказал или не ответил.
 *
 * Текст пишется для человека: его видит и менеджер в панели, и покупатель
 * на странице оплаты.
 */
class PaymentException extends RuntimeException {}
