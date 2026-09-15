<?php

namespace Nexor\PageBuilder\Blocks;

/**
 * Фото и галерея: готовые раскладки или своя сетка, лишние фото прячутся
 * под «+N» на последней видимой картинке.
 */
class PhotoBlock extends Block
{
    /** Раскладка → сколько фото видно. */
    public const PRESETS = ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '4_grid' => 4, '3_asym' => 3];

    public const ASYMMETRIC = ['top', 'bottom', 'left', 'right'];

    public function type(): string
    {
        return 'photo';
    }

    public function label(): string
    {
        return 'Фото / Галерея';
    }

    public function icon(): string
    {
        return 'image';
    }

    public function rules(): array
    {
        return [
            'layoutMode' => ['nullable', 'in:preset,custom'],
            'presetId' => ['nullable', 'in:'.implode(',', array_keys(self::PRESETS))],
            'asymmetricDir' => ['nullable', 'in:'.implode(',', self::ASYMMETRIC)],
            'customCols' => ['nullable', 'integer', 'min:1', 'max:12'],
            'customVisible' => ['nullable', 'integer', 'min:1', 'max:100'],
            'showOverlay' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:100'],
            'images.*.path' => ['required', 'string', 'max:500', self::PATH_RULE],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function defaults(): array
    {
        return [
            'images' => [],
            'layoutMode' => 'preset',
            'presetId' => '2',
            'asymmetricDir' => 'top',
            'customCols' => 4,
            'customVisible' => 4,
            'showOverlay' => true,
        ];
    }

    public function clean(array $data): array
    {
        return [
            'images' => array_values(array_filter(array_map($this->cleanMedia(...), $data['images'] ?? []))),
            'layoutMode' => $data['layoutMode'] ?? 'preset',
            'presetId' => (string) ($data['presetId'] ?? '2'),
            'asymmetricDir' => $data['asymmetricDir'] ?? 'top',
            'customCols' => (int) ($data['customCols'] ?? 4),
            'customVisible' => (int) ($data['customVisible'] ?? 4),
            'showOverlay' => $this->bool($data['showOverlay'] ?? null, true),
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

    /**
     * Шаблон получает уже посчитанные класс сетки и число видимых фото.
     */
    public function viewData(array $data): array
    {
        $data = $this->editorData($data);

        if ($data['layoutMode'] === 'custom') {
            $visibleCount = $data['customVisible'];
            $gridClass = 'pb-layout-custom';
            $customCols = $data['customCols'];
        } elseif ($data['presetId'] === '3_asym') {
            $visibleCount = 3;
            $gridClass = 'pb-layout-3_asym--'.$data['asymmetricDir'];
            $customCols = null;
        } else {
            $visibleCount = self::PRESETS[$data['presetId']] ?? 2;
            $gridClass = 'pb-layout-'.$data['presetId'];
            $customCols = null;
        }

        return $data + [
            'gridClass' => $gridClass,
            'customCols' => $customCols,
            'visibleCount' => $visibleCount,
            'hiddenCount' => count($data['images']) - $visibleCount,
        ];
    }
}
