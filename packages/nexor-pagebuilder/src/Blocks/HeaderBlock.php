<?php

namespace Nexor\PageBuilder\Blocks;

class HeaderBlock extends Block
{
    public const TAGS = ['h2', 'h3', 'h4', 'h5'];

    public function type(): string
    {
        return 'header';
    }

    public function label(): string
    {
        return 'Заголовок';
    }

    public function icon(): string
    {
        return 'heading';
    }

    public function rules(): array
    {
        return [
            'text' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'in:'.implode(',', self::TAGS)],
        ];
    }

    public function defaults(): array
    {
        return ['text' => '', 'tag' => 'h2'];
    }

    public function clean(array $data): array
    {
        return [
            'text' => trim((string) ($data['text'] ?? '')),
            'tag' => $data['tag'] ?? 'h2',
        ];
    }

    public function isEmpty(array $data): bool
    {
        return $data['text'] === '';
    }

    public function text(array $data): string
    {
        return $data['text'];
    }
}
