<?php

namespace Nexor\PageBuilder\Blocks;

/**
 * Слайдер картинок на Swiper.
 *
 * Сам Swiper модуль не везёт: сайт подключает его в макете, а скрипт модуля
 * оживляет `.js-pb-slider`. Без Swiper картинки просто стоят подряд.
 */
class SliderBlock extends Block
{
    public function type(): string
    {
        return 'slider';
    }

    public function label(): string
    {
        return 'Слайдер';
    }

    public function icon(): string
    {
        return 'gallery';
    }

    public function rules(): array
    {
        return [
            'slidesPerView' => ['nullable', 'integer', 'min:1', 'max:6'],
            'gap' => ['nullable', 'integer', 'min:0', 'max:100'],
            'autoplay' => ['nullable', 'boolean'],
            'arrows' => ['nullable', 'boolean'],
            'dots' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:100'],
            'images.*.path' => ['required', 'string', 'max:500', self::PATH_RULE],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function defaults(): array
    {
        return ['images' => [], 'slidesPerView' => 3, 'gap' => 20, 'autoplay' => false, 'arrows' => true, 'dots' => true];
    }

    public function clean(array $data): array
    {
        return [
            'images' => array_values(array_filter(array_map($this->cleanMedia(...), $data['images'] ?? []))),
            'slidesPerView' => (int) ($data['slidesPerView'] ?? 3),
            'gap' => (int) ($data['gap'] ?? 20),
            'autoplay' => $this->bool($data['autoplay'] ?? null, false),
            'arrows' => $this->bool($data['arrows'] ?? null, true),
            'dots' => $this->bool($data['dots'] ?? null, true),
        ];
    }

    public function isEmpty(array $data): bool
    {
        return $data['images'] === [];
    }

    public function text(array $data): string
    {
        return implode(' ', array_filter(array_column($data['images'], 'title')));
    }

    public function editorData(array $data): array
    {
        $data['images'] = array_values(array_filter(array_map($this->media(...), $data['images'] ?? [])));

        return $data;
    }

    public function viewData(array $data): array
    {
        return $this->editorData($data);
    }
}
