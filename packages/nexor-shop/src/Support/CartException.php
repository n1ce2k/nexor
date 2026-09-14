<?php

namespace Nexor\Shop\Support;

use RuntimeException;

/**
 * Товар нельзя положить в корзину. Сообщение показывается покупателю как есть.
 */
class CartException extends RuntimeException {}
