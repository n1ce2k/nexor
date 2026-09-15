<?php

namespace Nexor\PageBuilder\Blocks;

use Nexor\PageBuilder\Support\HtmlSanitizer;

class QuoteBlock extends Block
{
    public function type(): string
    {
        return 'quote';
    }

    public function label(): string
    {
        return 'Цитата';
    }

    public function icon(): string
    {
        return 'quote';
    }

    public function rules(): array
    {
        return [
            'text' => ['nullable', 'string', 'max:20000'],
            'showAuthor' => ['nullable', 'boolean'],
            'author' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            ...$this->mediaRules('avatar'),
        ];
    }

    public function defaults(): array
    {
        return ['text' => '', 'showAuthor' => false, 'author' => '', 'role' => '', 'avatar' => null];
    }

    public function clean(array $data): array
    {
        return [
            'text' => HtmlSanitizer::clean($data['text'] ?? ''),
            'showAuthor' => $this->bool($data['showAuthor'] ?? null, false),
            'author' => trim((string) ($data['author'] ?? '')),
            'role' => trim((string) ($data['role'] ?? '')),
            'avatar' => $this->cleanMedia($data['avatar'] ?? null),
        ];
    }

    public function isEmpty(array $data): bool
    {
        return $data['text'] === '';
    }

    public function text(array $data): string
    {
        return trim(HtmlSanitizer::text($data['text']).' '.$data['author']);
    }

    public function editorData(array $data): array
    {
        return ['avatar' => $this->media($data['avatar'] ?? null)] + $data;
    }

    public function viewData(array $data): array
    {
        return $this->editorData($data);
    }
}
