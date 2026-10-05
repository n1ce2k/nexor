<?php

namespace Nexor\Cms\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Кеш, который за один запрос ходит в хранилище за каждым ключом один раз.
 *
 * Настройки, блоки контента и меню страница спрашивает сотни раз. Обычный кеш
 * на каждый вопрос идёт в хранилище, а когда оно в базе или в файлах — это
 * сотни запросов и чтений с диска на страницу. Здесь прочитанное остаётся в
 * памяти до конца запроса; запись и сброс идут через тот же объект, поэтому
 * правка видна сразу.
 */
class RequestCache
{
    public static function store(): Repository
    {
        // Cache::memo() появился в Laravel 12.9; на более ранней сборке
        // остаётся обычный кеш — медленнее, но так же верно.
        return method_exists(Cache::getFacadeRoot(), 'memo') ? Cache::memo() : Cache::store();
    }
}
