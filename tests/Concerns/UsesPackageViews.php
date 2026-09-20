<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\View;

/**
 * Заставляет тест смотреть на шаблоны пакета, а не на копии сайта.
 *
 * Скопированный в `resources/views/vendor/...` шаблон принадлежит сайту, и
 * править его — законное право владельца. Но тест пакета проверяет пакет:
 * без этого чужая правка вёрстки роняет чужой же тест, и понять, что
 * сломалось, стоит вечера.
 */
trait UsesPackageViews
{
    protected function usePackageViews(string $namespace, string $path): void
    {
        View::prependNamespace($namespace, $path);
    }
}
