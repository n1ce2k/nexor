<?php

namespace Nexor\PageBuilder\Blocks;

use Closure;
use Nexor\PageBuilder\Support\HtmlSanitizer;
use Nexor\PageBuilder\Support\VideoEmbed;

/**
 * Видео по ссылке (YouTube, Vimeo, Rutube, VK Видео) или своим файлом.
 *
 * Вид: `full` — только плеер, `caption` — плеер и подпись под ним,
 * `split` — плеер и текст рядом (`reverse` меняет их местами).
 */
class VideoBlock extends Block
{
    public const LAYOUTS = ['full', 'caption', 'split'];

    public function type(): string
    {
        return 'video';
    }

    public function label(): string
    {
        return 'Видео';
    }

    public function icon(): string
    {
        return 'video';
    }

    public function rules(): array
    {
        return [
            'sourceType' => ['nullable', 'in:link,file'],
            'url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && VideoEmbed::parse($value) === null) {
                    $fail('Поддерживаются ссылки на YouTube, Vimeo, Rutube и VK Видео.');
                }
            }],
            ...$this->mediaRules('file'),
            ...$this->mediaRules('poster'),
            'layout' => ['nullable', 'in:'.implode(',', self::LAYOUTS)],
            'reverse' => ['nullable', 'boolean'],
            'text' => ['nullable', 'string', 'max:50000'],
            'settings' => ['nullable', 'array'],
            'settings.autoplay' => ['nullable', 'boolean'],
            'settings.loop' => ['nullable', 'boolean'],
            'settings.muted' => ['nullable', 'boolean'],
            'settings.controls' => ['nullable', 'boolean'],
        ];
    }

    public function defaults(): array
    {
        return [
            'sourceType' => 'link',
            'url' => '',
            'file' => null,
            'poster' => null,
            'layout' => 'full',
            'reverse' => false,
            'text' => '',
            'settings' => ['autoplay' => false, 'loop' => false, 'muted' => false, 'controls' => true],
        ];
    }

    public function clean(array $data): array
    {
        $sourceType = $data['sourceType'] ?? 'link';
        $settings = $data['settings'] ?? [];

        return [
            'sourceType' => $sourceType,
            'url' => $sourceType === 'link' ? trim((string) ($data['url'] ?? '')) : '',
            'file' => $sourceType === 'file' ? $this->cleanMedia($data['file'] ?? null) : null,
            'poster' => $sourceType === 'file' ? $this->cleanMedia($data['poster'] ?? null) : null,
            'layout' => $data['layout'] ?? 'full',
            'reverse' => $this->bool($data['reverse'] ?? null, false),
            'text' => HtmlSanitizer::clean($data['text'] ?? ''),
            'settings' => [
                'autoplay' => $this->bool($settings['autoplay'] ?? null, false),
                'loop' => $this->bool($settings['loop'] ?? null, false),
                'muted' => $this->bool($settings['muted'] ?? null, false),
                'controls' => $this->bool($settings['controls'] ?? null, true),
            ],
        ];
    }

    public function isEmpty(array $data): bool
    {
        return $data['sourceType'] === 'link' ? $data['url'] === '' : $data['file'] === null;
    }

    public function text(array $data): string
    {
        return HtmlSanitizer::text($data['text']);
    }

    public function editorData(array $data): array
    {
        return [
            'file' => $this->media($data['file'] ?? null),
            'poster' => $this->media($data['poster'] ?? null),
        ] + $data;
    }

    /**
     * Адрес плеера или файла и атрибуты тега `<video>` — посчитаны для шаблона.
     */
    public function viewData(array $data): array
    {
        $data = $this->editorData($data);
        $settings = $data['settings'];

        $embed = $data['sourceType'] === 'link' ? VideoEmbed::parse($data['url'], $settings) : null;

        $videoAttrs = array_keys(array_filter([
            'autoplay' => $settings['autoplay'],
            'loop' => $settings['loop'],
            'muted' => $settings['muted'],
            'controls' => $settings['controls'],
        ]));
        $videoAttrs[] = 'playsinline';

        $layoutClass = match ($data['layout']) {
            'caption' => 'pb-video--caption',
            'split' => 'pb-video--split'.($data['reverse'] ? ' pb-video--reverse' : ''),
            default => '',
        };

        return $data + [
            'videoUrl' => $embed['src'] ?? ($data['file']['src'] ?? ''),
            'isIframe' => $embed !== null,
            'videoAttrString' => implode(' ', $videoAttrs),
            'layoutClass' => $layoutClass,
        ];
    }
}
