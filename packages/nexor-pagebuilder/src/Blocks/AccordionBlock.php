<?php

namespace Nexor\PageBuilder\Blocks;

use Nexor\PageBuilder\Support\HtmlSanitizer;

/**
 * Аккордеон с разметкой FAQ (schema.org FAQPage).
 */
class AccordionBlock extends Block
{
    public function type(): string
    {
        return 'accordion';
    }

    public function label(): string
    {
        return 'Аккордеон';
    }

    public function icon(): string
    {
        return 'list';
    }

    public function rules(): array
    {
        return [
            'openFirst' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.title' => ['nullable', 'string', 'max:500'],
            'items.*.content' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public function defaults(): array
    {
        return ['items' => [['title' => '', 'content' => '']], 'openFirst' => true];
    }

    public function clean(array $data): array
    {
        $items = [];

        foreach ($data['items'] ?? [] as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $content = HtmlSanitizer::clean($item['content'] ?? '');

            // Пустая строка аккордеона — недописанная, а не часть контента.
            if ($title !== '' || $content !== '') {
                $items[] = ['title' => $title, 'content' => $content];
            }
        }

        return [
            'items' => $items,
            'openFirst' => $this->bool($data['openFirst'] ?? null, true),
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
