<?php

namespace Nexor\PageBuilder\Blocks;

use Closure;
use Nexor\PageBuilder\Support\HtmlSanitizer;

/**
 * Ссылки / Файлы: сетка карточек-ссылок.
 *
 * Вид `apps` — текст и картинка (приложения, сервисы), вид `docs` — иконка
 * и подпись (документы). Карточка ведёт по ссылке или на загруженный файл;
 * у документа можно включить скачивание.
 */
class LinkCardsBlock extends Block
{
    public const VARIANTS = ['apps', 'docs'];

    public function type(): string
    {
        return 'link_cards';
    }

    public function label(): string
    {
        return 'Ссылки / Файлы';
    }

    public function icon(): string
    {
        return 'links';
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:20000'],
            'variant' => ['nullable', 'in:'.implode(',', self::VARIANTS)],
            'cols' => ['nullable', 'integer', 'in:1,2,3,4'],
            'items' => ['nullable', 'array', 'max:60'],
            'items.*.text' => ['nullable', 'string', 'max:500'],
            'items.*.link' => ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && ! HtmlSanitizer::isSafeUrl($value)) {
                    $fail('Ссылка должна начинаться с http://, https://, mailto:, tel: или / — адрес на этом сайте.');
                }
            }],
            'items.*.isDownload' => ['nullable', 'boolean'],
            ...$this->mediaRules('items.*.image'),
            ...$this->mediaRules('items.*.file'),
        ];
    }

    public function defaults(): array
    {
        return ['description' => '', 'variant' => 'apps', 'cols' => 3, 'items' => []];
    }

    public function clean(array $data): array
    {
        $items = [];

        foreach ($data['items'] ?? [] as $item) {
            $clean = [
                'text' => trim((string) ($item['text'] ?? '')),
                'link' => trim((string) ($item['link'] ?? '')),
                'file' => $this->cleanMedia($item['file'] ?? null),
                'image' => $this->cleanMedia($item['image'] ?? null),
                'isDownload' => $this->bool($item['isDownload'] ?? null, false),
            ];

            if ($clean['text'] !== '' || $clean['link'] !== '' || $clean['file'] !== null) {
                $items[] = $clean;
            }
        }

        return [
            'description' => HtmlSanitizer::clean($data['description'] ?? ''),
            'variant' => $data['variant'] ?? 'apps',
            'cols' => (int) ($data['cols'] ?? 3),
            'items' => $items,
        ];
    }

    public function isEmpty(array $data): bool
    {
        return $data['items'] === [];
    }

    public function text(array $data): string
    {
        return trim(HtmlSanitizer::text($data['description']).' '.implode(' ', array_column($data['items'], 'text')));
    }

    public function editorData(array $data): array
    {
        $data['items'] = array_map(fn (array $item) => [
            'file' => $this->media($item['file'] ?? null),
            'image' => $this->media($item['image'] ?? null),
        ] + $item + ['text' => '', 'link' => '', 'isDownload' => false], $data['items'] ?? []);

        return $data;
    }

    /**
     * Шаблон получает готовый адрес карточки: загруженный файл важнее ссылки.
     */
    public function viewData(array $data): array
    {
        $data = $this->editorData($data);

        $data['items'] = array_map(fn (array $item) => $item + [
            'href' => $item['file']['src'] ?? ($item['link'] !== '' ? $item['link'] : '#'),
        ], $data['items']);

        return $data;
    }
}
