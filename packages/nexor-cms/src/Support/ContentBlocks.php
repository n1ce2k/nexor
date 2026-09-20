<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Nexor\Cms\Models\ContentBlock;

/**
 * Правки блоков, сделанные прямо на сайте.
 *
 * Содержимое по умолчанию живёт в шаблоне и в git, здесь лежат только
 * переопределения: есть запись — показываем её, нет — то, что написано в
 * шаблоне. Поэтому шаблон остаётся читаемым без базы, а «вернуть как было» —
 * это удаление записи, а не поиск старого текста.
 *
 * Хранилище выбирается в `.env`: `NEXOR_CONTENT_DB=true` — таблица,
 * `false` — JSON-файл в `storage/app`. Оба драйвера отдают одинаковую карту
 * `ключ => [type, value]` и одинаково кешируются: блоки читаются на каждой
 * странице сайта, запросом на блок это быть не должно.
 */
class ContentBlocks
{
    public const CACHE_KEY = 'nexor.content-blocks';

    /** Что умеет править редактор на сайте. */
    public const TYPES = ['text', 'html', 'image'];

    /**
     * Все правки разом.
     *
     * @return array<string, array{type: string, value: string|null}>
     */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::read());
    }

    /**
     * Значение блока или null, если его не правили.
     */
    public static function get(string $key): ?string
    {
        $block = self::all()[$key] ?? null;

        return $block['value'] ?? null;
    }

    /**
     * Сохраняет правку.
     */
    public static function put(string $key, string $type, string $value, ?int $userId = null): void
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'text';

        self::usesDatabase()
            ? ContentBlock::query()->updateOrCreate(
                ['key' => $key],
                ['type' => $type, 'value' => $value, 'updated_by' => $userId],
            )
            : self::write([...self::read(), $key => ['type' => $type, 'value' => $value]]);

        self::forgetCache();
    }

    /**
     * Убирает правку — блок снова показывает то, что в шаблоне.
     */
    public static function forget(string $key): void
    {
        if (self::usesDatabase()) {
            ContentBlock::query()->where('key', $key)->delete();
        } else {
            $stored = self::read();
            unset($stored[$key]);

            self::write($stored);
        }

        self::forgetCache();
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Правки хранятся в базе или в файле.
     */
    public static function usesDatabase(): bool
    {
        return (bool) config('nexor.content.database', true);
    }

    public static function file(): string
    {
        return storage_path('app/'.config('nexor.content.file', 'nexor-content.json'));
    }

    /**
     * Чтение из выбранного хранилища, мимо кеша.
     *
     * @return array<string, array{type: string, value: string|null}>
     */
    protected static function read(): array
    {
        if (! self::usesDatabase()) {
            return self::readFile();
        }

        // Сайт может открыться до первой миграции — это не повод падать.
        if (! Schema::hasTable('content_blocks')) {
            return [];
        }

        return ContentBlock::query()
            ->get(['key', 'type', 'value'])
            ->mapWithKeys(fn (ContentBlock $block) => [
                $block->key => ['type' => $block->type, 'value' => $block->value],
            ])
            ->all();
    }

    /**
     * @return array<string, array{type: string, value: string|null}>
     */
    protected static function readFile(): array
    {
        if (! File::exists(self::file())) {
            return [];
        }

        $decoded = json_decode((string) File::get(self::file()), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array{type: string, value: string|null}>  $blocks
     */
    protected static function write(array $blocks): void
    {
        File::ensureDirectoryExists(dirname(self::file()));
        File::put(self::file(), json_encode($blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
