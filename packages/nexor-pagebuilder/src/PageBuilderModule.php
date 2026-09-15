<?php

namespace Nexor\PageBuilder;

use Closure;
use Illuminate\Validation\ValidationException;
use Nexor\Cms\Enums\License;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Support\Modules\Module;
use Nexor\PageBuilder\Support\LayoutDocument;

/**
 * Модуль «Конструктор страниц».
 *
 * Сейчас: детальная страница элемента собирается из блоков вместо подробного
 * текста — включается у инфоблока. Дальше: страницы сайта целиком (см. README).
 */
class PageBuilderModule extends Module
{
    public const VERSION = '0.1.0';

    public function code(): string
    {
        return PageBuilder::MODULE;
    }

    public function name(): string
    {
        return 'Конструктор страниц';
    }

    public function description(): string
    {
        return 'Детальная страница элемента из блоков: заголовки, текст, фото, видео, аккордеон, таблицы.';
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function license(): License
    {
        return License::Lite;
    }

    public function features(): array
    {
        return [
            'pagebuilder.detail' => ['label' => 'Конструктор детальной страницы', 'license' => License::Lite],
        ];
    }

    public function iblockSettings(): array
    {
        return [
            PageBuilder::DETAIL_SETTING => [
                'label' => 'Использовать конструктор детальной страницы',
                'hint' => 'У элементов появится вкладка «Конструктор»: страница собирается из блоков вместо подробного текста.',
                'default' => false,
            ],
        ];
    }

    public function elementFields(Iblock $iblock): array
    {
        if (! $iblock->moduleSetting(PageBuilder::MODULE, PageBuilder::DETAIL_SETTING, false)) {
            return [];
        }

        return [
            'content' => ['label' => 'Блоки страницы', 'tab' => 'pagebuilder', 'tab_label' => 'Конструктор'],
        ];
    }

    public function elementRules(Iblock $iblock): array
    {
        return [
            'content' => ['nullable', 'string', 'max:2000000', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    LayoutDocument::normalize($value);
                } catch (ValidationException $exception) {
                    $fail(collect($exception->errors())->flatten()->first());
                }
            }],
        ];
    }

    public function elementValues(IblockElement $element): array
    {
        return ['content' => PageBuilder::documentOf($element)];
    }

    public function saveElement(IblockElement $element, array $values): void
    {
        if (array_key_exists('content', $values)) {
            PageBuilder::save($element, $values['content']);
        }
    }

    public function panelAssets(): ?array
    {
        $root = dirname(__DIR__);

        return [
            'dist' => $root.'/dist',
            // Утилиты Tailwind редактора собираются в стили ядра, как у магазина.
            'script' => 'panel.js',
            'source' => $root.'/resources/js/panel.js',
        ];
    }

    public function apiRoutes(): ?string
    {
        return dirname(__DIR__).'/routes/api.php';
    }
}
