<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Фон секции, который меняют прямо на странице.
 *
 * ```blade
 * <section class="main_section" @editBackground('main.bg', '/img/hero.jpg')>
 * <section class="main_section" @editBackground('main.bg', '/img/hero.jpg', var: '--main-bg')>
 * ```
 *
 * Директива пишет атрибуты прямо в тег: секцию не надо оборачивать, и вся её
 * вёрстка остаётся как была. Без `var` выводится `background-image` — позиция,
 * размер и повтор остаются в CSS. С `var` выводится только CSS-переменная, а
 * как накладывать картинку (градиент поверх, другая на мобильном) решают стили.
 *
 * Свой `style` у того же тега не ставьте: второй атрибут браузер проигнорирует.
 */
class InlineBackground
{
    public static function attributes(string $key, ?string $default = null, ?string $var = null): HtmlString
    {
        if ($var !== null && preg_match('/^--[A-Za-z0-9_-]+$/', $var) !== 1) {
            throw new InvalidArgumentException("@editBackground('{$key}'): переменная должна выглядеть как --main-bg, а не «{$var}».");
        }

        $editing = InlineEditor::active();
        $stored = ContentBlocks::get($key);

        if ($editing) {
            ContentBlocks::remember($key, 'image', (string) $default);
        }

        $url = $stored ? Uploads::url($stored) : $default;
        $parts = [];

        if ($url !== null && $url !== '') {
            // Без кавычек: всё, что могло бы закрыть url(), уже закодировано.
            $value = 'url('.self::cssUrl($url).')';
            $parts[] = 'style="'.e($var !== null ? $var.': '.$value : 'background-image: '.$value).'"';
        }

        // Секции не ставится data-nexor-edit: иначе клик по любому месту внутри
        // неё открывал бы правку текста. Скрипт находит её по своей пометке и
        // вешает в угол отдельную кнопку.
        if ($editing) {
            $parts[] = 'data-nexor-bg="'.e($key).'"';

            if ($var !== null) {
                $parts[] = 'data-nexor-bg-var="'.e($var).'"';
            }

            if ($stored) {
                $parts[] = 'data-nexor-edited="1"';
            }
        }

        return new HtmlString(implode(' ', $parts));
    }

    /**
     * Адрес, безопасный внутри `url(...)`.
     *
     * Кавычка, скобка или обратная косая в адресе закрыли бы строку CSS, и
     * дальше пошло бы что угодно — поэтому они кодируются.
     */
    public static function cssUrl(string $url): string
    {
        return str_replace(
            ["'", '"', '\\', '(', ')', ' ', "\n", "\r"],
            ['%27', '%22', '%5C', '%28', '%29', '%20', '', ''],
            $url,
        );
    }
}
