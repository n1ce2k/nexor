<?php

namespace Tests\Feature;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Nexor\Cms\Models\Setting;
use Tests\TestCase;

/**
 * Настройки за запрос читаются из хранилища кеша один раз.
 *
 * Страница спрашивает их сотни раз; когда кеш лежит в базе, каждый вопрос
 * был отдельным SQL-запросом.
 */
class RequestCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_reach_the_cache_store_once_per_request(): void
    {
        Setting::put('site.name', 'Сайт');
        Setting::get('site.name');

        $reads = 0;
        Event::listen([CacheHit::class, CacheMissed::class], function (CacheHit|CacheMissed $event) use (&$reads): void {
            if ($event->key === Setting::CACHE_KEY) {
                $reads++;
            }
        });

        foreach (range(1, 50) as $_) {
            $this->assertSame('Сайт', Setting::get('site.name'));
        }

        // Одно чтение — после записи; дальше значение живёт в памяти запроса.
        $this->assertLessThanOrEqual(1, $reads);
    }

    public function test_saved_setting_is_visible_in_the_same_request(): void
    {
        Setting::put('site.name', 'Было');
        $this->assertSame('Было', Setting::get('site.name'));

        Setting::put('site.name', 'Стало');

        $this->assertSame('Стало', Setting::get('site.name'));
    }
}
