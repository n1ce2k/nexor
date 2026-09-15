<?php

namespace Nexor\PageBuilder\Blocks;

use Nexor\PageBuilder\Support\HtmlSanitizer;

/**
 * Текст в одну или две колонки.
 */
class TextBlock extends Block
{
    public function type(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Текст';
    }

    public function icon(): string
    {
        return 'text';
    }

    public function rules(): array
    {
        return [
            'text' => ['nullable', 'string', 'max:200000'],
            'text2' => ['nullable', 'string', 'max:200000'],
            'cols' => ['nullable', 'integer', 'in:1,2'],
        ];
    }

    public function defaults(): array
    {
        return ['text' => '', 'text2' => '', 'cols' => 1];
    }

    public function clean(array $data): array
    {
        $cols = (int) ($data['cols'] ?? 1);

        return [
            'text' => HtmlSanitizer::clean($data['text'] ?? ''),
            // Вторая колонка хранится, только пока колонок две.
            'text2' => $cols === 2 ? HtmlSanitizer::clean($data['text2'] ?? '') : '',
            'cols' => $cols,
        ];
    }

    public function isEmpty(array $data): bool
    {
        return $data['text'] === '';
    }

    public function text(array $data): string
    {
        return trim(HtmlSanitizer::text($data['text']).' '.HtmlSanitizer::text($data['text2']));
    }
}
