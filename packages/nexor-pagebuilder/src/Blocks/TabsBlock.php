<?php

namespace Nexor\PageBuilder\Blocks;

use Illuminate\Support\Str;
use Nexor\PageBuilder\Support\HtmlSanitizer;

/**
 * Табы: у каждой вкладки заголовок и визуальный текст, одна открыта сразу.
 */
class TabsBlock extends Block
{
    public function type(): string
    {
        return 'tabs';
    }

    public function label(): string
    {
        return 'Табы';
    }

    public function icon(): string
    {
        return 'tabs';
    }

    public function rules(): array
    {
        return [
            'activeTabId' => ['nullable', 'string', 'max:40'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.content' => ['nullable', 'string', 'max:100000'],
        ];
    }

    public function defaults(): array
    {
        return ['items' => [], 'activeTabId' => null];
    }

    public function clean(array $data): array
    {
        $items = [];
        $ids = [];

        foreach ($data['items'] ?? [] as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $content = HtmlSanitizer::clean($item['content'] ?? '');

            if ($title === '' && $content === '') {
                continue;
            }

            $id = (string) ($item['id'] ?? '') ?: Str::random(8);

            while (isset($ids[$id])) {
                $id = Str::random(8);
            }

            $ids[$id] = true;
            $items[] = ['id' => $id, 'title' => $title, 'content' => $content];
        }

        $active = (string) ($data['activeTabId'] ?? '');

        return [
            'items' => $items,
            // Открытая вкладка — только из существующих, иначе первая.
            'activeTabId' => isset($ids[$active]) ? $active : ($items[0]['id'] ?? null),
        ];
    }

    public function isEmpty(array $data): bool
    {
        return $data['items'] === [];
    }

    public function text(array $data): string
    {
        return implode(' ', array_map(
            fn (array $item) => $item['title'].' '.HtmlSanitizer::text($item['content']),
            $data['items'],
        ));
    }
}
