<?php

namespace Nexor\Shop\Support\Delivery;

use RuntimeException;

/**
 * Служба доставки не ответила или отказала.
 *
 * Сообщение показывается покупателю на оформлении, поэтому оно по-русски и без
 * технических подробностей — их пишем в лог.
 */
class DeliveryException extends RuntimeException {}
