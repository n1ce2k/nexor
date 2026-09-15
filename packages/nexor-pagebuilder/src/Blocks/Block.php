<?php

namespace Nexor\PageBuilder\Blocks;

use Nexor\Cms\Support\Uploads;
use Nexor\PageBuilder\Enums\Surface;

/**
 * Один тип блока конструктора: как проверить его данные, как очистить и чем вывести.
 *
 * Свой блок — класс-наследник и регистрация в провайдере:
 *
 *     PageBuilder::blocks()->register(new PromoBlock);
 *
 * Шаблон на сайте — `nexor-pagebuilder::blocks.<type>`, забирается к себе
 * командой `nexor-pagebuilder:component <type>`. Редактор в панели —
 * `window.Nexor.pageBuilder.registerEditor('<type>', Component)`.
 *
 * У каждого блока есть `cssClass` — дополнительный класс секции `pb-block`;
 * его проверяет и хранит `LayoutDocument`, самим блокам о нём думать не нужно.
 */
abstract class Block
{
    /** Символьный код блока в JSON раскладки. */
    abstract public function type(): string;

    abstract public function label(): string;

    /**
     * Правила проверки `data` блока, ключи — относительно самих данных.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /** Иконка в палитре. */
    public function icon(): string
    {
        return 'document';
    }

    /**
     * Где блок доступен. Блоки, которым нужна вся ширина страницы (баннеры,
     * витрины), будут объявлять только `Surface::Page`.
     *
     * @return array<int, Surface>
     */
    public function surfaces(): array
    {
        return [Surface::Detail, Surface::Page];
    }

    /**
     * Данные нового блока — с ними он появляется в редакторе.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [];
    }

    /**
     * Проверенные данные → то, что сохраняется: чистый HTML, приведённые типы.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function clean(array $data): array
    {
        return $data;
    }

    /**
     * Пустой ли блок: такой не сохраняется и не выводится.
     *
     * @param  array<string, mixed>  $data
     */
    public function isEmpty(array $data): bool
    {
        return false;
    }

    /**
     * Текст блока для поиска по сайту.
     *
     * @param  array<string, mixed>  $data
     */
    public function text(array $data): string
    {
        return '';
    }

    /**
     * Сохранённые данные поверх умолчаний — чтобы шаблон и редактор не
     * падали на ключе, которого не было, когда блок сохраняли.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepare(array $data): array
    {
        return array_replace($this->defaults(), ['cssClass' => ''], $data);
    }

    public function view(): string
    {
        return 'nexor-pagebuilder::blocks.'.$this->type();
    }

    /**
     * Что получает шаблон на сайте. По умолчанию — сами данные.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function viewData(array $data): array
    {
        return $data;
    }

    /**
     * Что получает редактор в панели: сохранённые данные плюс то, что нужно
     * только для показа (адреса файлов). Лишнее при сохранении отбросит `clean()`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function editorData(array $data): array
    {
        return $data;
    }

    /**
     * Описание для палитры в панели.
     *
     * @return array{type: string, label: string, icon: string, defaults: array<string, mixed>|\stdClass}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'label' => $this->label(),
            'icon' => $this->icon(),
            'defaults' => $this->defaults() ?: new \stdClass,
        ];
    }

    /**
     * Правила файла из загрузки конструктора: путь в хранилище, alt и title.
     *
     * @return array<string, mixed>
     */
    protected function mediaRules(string $key): array
    {
        return [
            $key => ['nullable', 'array'],
            "{$key}.path" => ['nullable', 'string', 'max:500', self::PATH_RULE],
            "{$key}.alt" => ['nullable', 'string', 'max:255'],
            "{$key}.title" => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Путь внутри хранилища: без `..`, схемы и абсолютного пути. */
    protected const PATH_RULE = 'not_regex:/\.\.|^\/|\\\\|^[a-z]+:/i';

    /**
     * Файл для сохранения: `{ path, alt, title }` или null.
     *
     * @param  mixed  $media
     * @return array{path: string, alt: string, title: string}|null
     */
    protected function cleanMedia($media): ?array
    {
        if (! is_array($media) || empty($media['path'])) {
            return null;
        }

        return [
            'path' => (string) $media['path'],
            'alt' => trim((string) ($media['alt'] ?? '')),
            'title' => trim((string) ($media['title'] ?? '')),
        ];
    }

    /**
     * Файл для шаблона и редактора: `{ src, alt, title }` — как в примере
     * вёрстки, где у картинки всегда есть `src`.
     *
     * @param  array{path?: string, alt?: string, title?: string}|null  $media
     * @return array{path: string, src: string, alt: string, title: string}|null
     */
    protected function media(?array $media): ?array
    {
        if (empty($media['path'])) {
            return null;
        }

        return [
            'path' => $media['path'],
            'src' => (string) Uploads::url($media['path']),
            'alt' => (string) ($media['alt'] ?? ''),
            'title' => (string) ($media['title'] ?? ''),
        ];
    }

    protected function bool(mixed $value, bool $default): bool
    {
        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
