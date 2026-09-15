<?php

namespace Nexor\PageBuilder;

use Illuminate\Support\HtmlString;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Support\Nexor;
use Nexor\PageBuilder\Blocks\BlockRegistry;
use Nexor\PageBuilder\Enums\Surface;
use Nexor\PageBuilder\Models\Layout;
use Nexor\PageBuilder\Support\LayoutDocument;

/**
 * Точка входа модуля: реестр блоков, чтение, сохранение и вывод раскладок.
 */
class PageBuilder
{
    /** Код модуля и его функции «конструктор детальной страницы». */
    public const MODULE = 'pagebuilder';

    /** Ключ переключателя инфоблока: `settings.modules.pagebuilder.detail`. */
    public const DETAIL_SETTING = 'detail';

    public static function blocks(): BlockRegistry
    {
        return app(BlockRegistry::class);
    }

    /**
     * Включён ли конструктор детальной страницы у инфоблока этого элемента.
     */
    public static function enabledFor(IblockElement $element): bool
    {
        $iblock = $element->iblock;

        return $iblock !== null
            && Nexor::feature(self::MODULE)
            && (bool) $iblock->moduleSetting(self::MODULE, self::DETAIL_SETTING, false);
    }

    public static function layoutOf(IblockElement $element): ?Layout
    {
        return Layout::query()->ownedBy(Layout::OWNER_ELEMENT, $element->id)->first();
    }

    /**
     * Документ раскладки для формы. Пустая раскладка — пустой список блоков.
     *
     * @return array{version: int, settings: array<string, mixed>, blocks: array<int, mixed>}
     */
    public static function documentOf(IblockElement $element): array
    {
        $document = self::layoutOf($element)?->content ?? [
            'version' => LayoutDocument::VERSION,
            'settings' => [],
            'blocks' => [],
        ];

        $document['blocks'] = array_values(array_filter(array_map(function (array $item): ?array {
            $block = self::blocks()->find($item['type'] ?? '');

            return $block ? ['data' => $block->editorData($block->prepare((array) ($item['data'] ?? [])))] + $item : null;
        }, $document['blocks'] ?? [])));

        return $document;
    }

    /**
     * Сохраняет раскладку элемента. Без единого блока запись удаляется —
     * на сайте снова выводится подробный текст.
     */
    public static function save(IblockElement $element, mixed $input): ?Layout
    {
        $document = LayoutDocument::normalize($input);

        if ($document['blocks'] === []) {
            Layout::query()->ownedBy(Layout::OWNER_ELEMENT, $element->id)->delete();

            return null;
        }

        return Layout::query()->updateOrCreate(
            ['owner_type' => Layout::OWNER_ELEMENT, 'owner_id' => $element->id, 'surface' => Surface::Detail->value],
            ['content' => $document, 'search_text' => LayoutDocument::text($document)],
        );
    }

    /**
     * Есть ли что вывести вместо подробного текста.
     */
    public static function hasContent(IblockElement $element): bool
    {
        return self::enabledFor($element) && (self::layoutOf($element)?->blocks() ?? []) !== [];
    }

    /**
     * HTML блоков элемента. Пустая строка, если конструктор выключен или пуст.
     *
     * `assets` — подключить стили и скрипт модуля (аккордеон, галерея,
     * оглавление). Сайт со своей вёрсткой классов `pb-*` передаёт `false`
     * или выключает их везде через `NEXOR_PAGEBUILDER_ASSETS=false`.
     *
     * @param  array{assets?: bool}  $options
     */
    public static function render(IblockElement $element, array $options = []): HtmlString
    {
        if (! self::enabledFor($element)) {
            return new HtmlString('');
        }

        $layout = self::layoutOf($element);
        $items = self::items($layout?->blocks() ?? []);

        if ($items === []) {
            return new HtmlString('');
        }

        $settings = $layout->content['settings'] ?? [];
        $sidebarItems = $settings['sidebarItems'] ?? [];
        $assets = (bool) ($options['assets'] ?? config('nexor-pagebuilder.assets', true));

        $html = view('nexor-pagebuilder::content', [
            'items' => $items,
            'element' => $element,
            'showSidebar' => ! empty($settings['showSidebar']) && $sidebarItems !== [],
            'sidebarItems' => $sidebarItems,
            'standalone' => $assets,
        ])->render();

        return new HtmlString(($assets ? self::assets() : '').$html);
    }

    /**
     * Теги стилей и скрипта модуля — один раз на страницу.
     */
    public static function assets(): string
    {
        $request = request();

        if ($request->attributes->get('nexor-pagebuilder.assets')) {
            return '';
        }

        $request->attributes->set('nexor-pagebuilder.assets', true);

        return '<link rel="stylesheet" href="'.e(self::assetUrl('pagebuilder.css')).'">'
            .'<script src="'.e(self::assetUrl('pagebuilder.js')).'" defer></script>';
    }

    public static function assetUrl(string $file): string
    {
        $path = dirname(__DIR__).'/resources/assets/'.$file;

        return route('nexor-pagebuilder.assets', ['file' => $file]).(is_file($path) ? '?v='.filemtime($path) : '');
    }

    /**
     * Блоки, готовые для шаблона: вид и данные. Тип, которого больше нет
     * (сайт убрал свой блок), пропускается, а не роняет страницу.
     *
     * @param  array<int, array{id: string, type: string, data: array<string, mixed>}>  $blocks
     * @return array<int, array{id: string, type: string, view: string, data: array<string, mixed>}>
     */
    public static function items(array $blocks): array
    {
        $items = [];

        foreach ($blocks as $item) {
            $block = self::blocks()->find($item['type'] ?? '');

            if (! $block) {
                continue;
            }

            $items[] = [
                'id' => $item['id'],
                'type' => $block->type(),
                'view' => $block->view(),
                'data' => $block->viewData($block->prepare((array) ($item['data'] ?? []))),
            ];
        }

        return $items;
    }
}
