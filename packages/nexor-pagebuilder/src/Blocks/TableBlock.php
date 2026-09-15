<?php

namespace Nexor\PageBuilder\Blocks;

/**
 * Таблица хранится ячейками, а не HTML: так её не сломать вставленной
 * разметкой. На сайт выходит обычный `<table>` внутри `pb-table__responsive`.
 */
class TableBlock extends Block
{
    public const MAX_ROWS = 200;

    public const MAX_COLUMNS = 12;

    public const TITLE_TAGS = ['h2', 'h3', 'h4', 'h5'];

    public function type(): string
    {
        return 'table';
    }

    public function label(): string
    {
        return 'Таблица';
    }

    public function icon(): string
    {
        return 'table';
    }

    public function rules(): array
    {
        return [
            'showTitle' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'titleTag' => ['nullable', 'in:'.implode(',', self::TITLE_TAGS)],
            'header' => ['nullable', 'boolean'],
            'rows' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'rows.*' => ['array', 'max:'.self::MAX_COLUMNS],
            'rows.*.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function defaults(): array
    {
        return [
            'showTitle' => false,
            'title' => '',
            'titleTag' => 'h3',
            'header' => true,
            'rows' => [['', ''], ['', '']],
        ];
    }

    public function clean(array $data): array
    {
        $rows = array_map(
            fn (array $row) => array_map(fn ($cell) => trim((string) $cell), array_values($row)),
            array_values($data['rows'] ?? []),
        );

        // Ряды одной ширины — иначе таблица на сайте поедет.
        $width = max([1, ...array_map('count', $rows)]);
        $rows = array_map(fn (array $row) => array_pad($row, $width, ''), $rows);

        // Полностью пустые ряды в конце — хвост недописанной таблицы.
        while ($rows !== [] && implode('', end($rows)) === '') {
            array_pop($rows);
        }

        return [
            'showTitle' => $this->bool($data['showTitle'] ?? null, false),
            'title' => trim((string) ($data['title'] ?? '')),
            'titleTag' => $data['titleTag'] ?? 'h3',
            'header' => $this->bool($data['header'] ?? null, true),
            'rows' => $rows,
        ];
    }

    public function isEmpty(array $data): bool
    {
        return $data['rows'] === [];
    }

    public function text(array $data): string
    {
        return trim($data['title'].' '.implode(' ', array_map(fn (array $row) => implode(' ', $row), $data['rows'])));
    }
}
