<?php

namespace Nexor\PageBuilder\Blocks;

use Nexor\PageBuilder\Support\HtmlSanitizer;

class TextImageBlock extends Block
{
    public function type(): string
    {
        return 'text_image';
    }

    public function label(): string
    {
        return 'Текст + Фото';
    }

    public function icon(): string
    {
        return 'panel-left';
    }

    public function rules(): array
    {
        return [
            'text' => ['nullable', 'string', 'max:100000'],
            'imagePosition' => ['nullable', 'in:left,right'],
            ...$this->mediaRules('image'),
        ];
    }

    public function defaults(): array
    {
        return ['text' => '', 'image' => null, 'imagePosition' => 'left'];
    }

    public function clean(array $data): array
    {
        return [
            'text' => HtmlSanitizer::clean($data['text'] ?? ''),
            'image' => $this->cleanMedia($data['image'] ?? null),
            'imagePosition' => $data['imagePosition'] ?? 'left',
        ];
    }

    public function isEmpty(array $data): bool
    {
        return $data['text'] === '' && $data['image'] === null;
    }

    public function text(array $data): string
    {
        return HtmlSanitizer::text($data['text']);
    }

    public function editorData(array $data): array
    {
        return ['image' => $this->media($data['image'] ?? null)] + $data;
    }

    public function viewData(array $data): array
    {
        return $this->editorData($data);
    }
}
