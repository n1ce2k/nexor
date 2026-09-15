<?php

namespace Nexor\PageBuilder\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Nexor\PageBuilder\Blocks\Block;
use Nexor\PageBuilder\Enums\Surface;
use Nexor\PageBuilder\PageBuilder;

/**
 * Документ раскладки: проверка и приведение к сохраняемому виду.
 *
 *     {
 *       "version": 1,
 *       "settings": { "showSidebar": true, "sidebarItems": [ { "text": "…", "targetId": "b1" } ] },
 *       "blocks": [ { "id": "b1", "type": "text", "data": { …, "cssClass": "…" } } ]
 *     }
 *
 * `settings` — параметры всей раскладки. Сейчас это боковое оглавление
 * (`pb-aside`); у будущих страниц-конструкторов туда же лягут ширина и фон.
 */
class LayoutDocument
{
    public const VERSION = 1;

    public const MAX_BLOCKS = 200;

    /** Дополнительные классы секции: буквы, цифры, дефис, подчёркивание, пробел. */
    protected const CSS_CLASS = ['nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9_\- ]*$/'];

    /**
     * Приводит пришедшее из формы к документу.
     *
     * @return array{version: int, settings: array{showSidebar: bool, sidebarItems: array<int, array{text: string, targetId: string}>}, blocks: array<int, array{id: string, type: string, data: array<string, mixed>}>}
     *
     * @throws ValidationException Сообщения уже по-человечески: «Блок 3 «Видео»: …»
     */
    public static function normalize(mixed $input, Surface $surface = Surface::Detail): array
    {
        $document = is_string($input) ? json_decode($input, true) : $input;

        if ($input === null || $input === '') {
            $document = [];
        }

        if (! is_array($document)) {
            self::fail('Раскладка блоков пришла в неверном формате.');
        }

        $blocks = $document['blocks'] ?? [];

        if (! is_array($blocks) || ! array_is_list($blocks)) {
            self::fail('Раскладка блоков пришла в неверном формате.');
        }

        if (count($blocks) > self::MAX_BLOCKS) {
            self::fail('Слишком много блоков: не больше '.self::MAX_BLOCKS.'.');
        }

        $available = PageBuilder::blocks()->for($surface);
        $clean = [];
        $ids = [];

        foreach ($blocks as $index => $item) {
            $number = $index + 1;
            $type = is_array($item) ? (string) ($item['type'] ?? '') : '';
            $block = $available[$type] ?? null;

            if (! $block) {
                self::fail("Блок {$number}: тип «{$type}» здесь недоступен.");
            }

            $data = self::validateData($block, is_array($item['data'] ?? null) ? $item['data'] : [], $number);

            if ($block->isEmpty($data)) {
                continue;
            }

            // id нужен редактору и якорям оглавления (`#block-<id>`).
            $id = is_scalar($item['id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) $item['id'])
                ? (string) $item['id']
                : Str::random(10);

            while (isset($ids[$id])) {
                $id = Str::random(10);
            }

            $ids[$id] = true;
            $clean[] = ['id' => $id, 'type' => $type, 'data' => $data];
        }

        return [
            'version' => self::VERSION,
            'settings' => self::settings(is_array($document['settings'] ?? null) ? $document['settings'] : [], $ids),
            'blocks' => $clean,
        ];
    }

    /**
     * Текст всех блоков — для поиска.
     *
     * @param  array{blocks?: array<int, array{type: string, data: array<string, mixed>}>}  $document
     */
    public static function text(array $document): string
    {
        $parts = [];

        foreach ($document['blocks'] ?? [] as $item) {
            if ($block = PageBuilder::blocks()->find($item['type'])) {
                $parts[] = $block->text($item['data']);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts))) ?? '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function validateData(Block $block, array $data, int $number): array
    {
        $validator = Validator::make($data, $block->rules() + ['cssClass' => self::CSS_CLASS]);

        if ($validator->fails()) {
            self::fail("Блок {$number} «{$block->label()}»: ".$validator->errors()->first());
        }

        $validated = $validator->validated();

        return $block->clean($validated) + [
            'cssClass' => trim(preg_replace('/\s+/', ' ', (string) ($validated['cssClass'] ?? '')) ?? ''),
        ];
    }

    /**
     * Оглавление: пункты только на блоки, которые остались в раскладке.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, true>  $ids
     * @return array{showSidebar: bool, sidebarItems: array<int, array{text: string, targetId: string}>}
     */
    protected static function settings(array $settings, array $ids): array
    {
        $validator = Validator::make($settings, [
            'showSidebar' => ['nullable', 'boolean'],
            'sidebarItems' => ['nullable', 'array', 'max:50'],
            'sidebarItems.*.text' => ['nullable', 'string', 'max:255'],
            'sidebarItems.*.targetId' => ['nullable', 'string', 'max:40'],
        ]);

        if ($validator->fails()) {
            self::fail('Оглавление: '.$validator->errors()->first());
        }

        $items = [];

        foreach ($settings['sidebarItems'] ?? [] as $item) {
            $text = trim((string) ($item['text'] ?? ''));
            $target = (string) ($item['targetId'] ?? '');

            if ($text !== '' && isset($ids[$target])) {
                $items[] = ['text' => $text, 'targetId' => $target];
            }
        }

        return [
            'showSidebar' => filter_var($settings['showSidebar'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sidebarItems' => $items,
        ];
    }

    protected static function fail(string $message): never
    {
        throw ValidationException::withMessages(['content' => $message]);
    }
}
