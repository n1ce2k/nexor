<?php

namespace Nexor\PageBuilder\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Очистка HTML из визуального редактора по белому списку.
 *
 * Блоки правят не только администраторы, но и контент-редакторы, поэтому
 * разметка проверяется при сохранении: остаются теги форматирования текста,
 * ссылки только с безопасной схемой, никаких скриптов, стилей и обработчиков.
 * Незнакомый тег разворачивается — его текст остаётся, а сам тег пропадает.
 */
class HtmlSanitizer
{
    /**
     * Разрешённые теги и их атрибуты.
     *
     * @var array<string, array<int, string>>
     */
    protected const ALLOWED = [
        'p' => ['class'],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'sub' => [],
        'sup' => [],
        'a' => ['href', 'target', 'rel'],
        'ul' => [],
        'ol' => [],
        'li' => ['class', 'data-list'],
        'h2' => ['class'],
        'h3' => ['class'],
        'h4' => ['class'],
        'h5' => ['class'],
        'blockquote' => [],
        'pre' => [],
        'code' => [],
        'span' => [],
    ];

    /**
     * Теги, которые удаляются вместе с содержимым.
     *
     * @var array<int, string>
     */
    protected const DROPPED = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button',
        'textarea', 'select', 'template', 'noscript', 'link', 'meta', 'base', 'frame', 'frameset',
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // Объявление кодировки — иначе libxml прочитает кириллицу как Latin-1.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;

        foreach ($document->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $root = $node;

                break;
            }
        }

        if (! $root) {
            return '';
        }

        self::cleanChildren($root);

        $result = '';

        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return self::isBlank($result) ? '' : trim($result);
    }

    /**
     * Есть ли в разметке хоть какой-то текст: `<p><br></p>` пустой.
     */
    public static function isBlank(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{A0}") === '';
    }

    /**
     * Текст без разметки — для поиска.
     */
    public static function text(?string $html): string
    {
        $text = strip_tags(str_replace(['<br>', '</p>', '</li>', '</h2>', '</h3>', '</h4>', '</h5>'], ' ', (string) $html));

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    protected static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // Комментарии, инструкции обработки и прочее.
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED, true)) {
                $node->removeChild($child);

                continue;
            }

            // Служебная разметка Quill внутри пунктов списка.
            if ($tag === 'span' && str_contains((string) $child->getAttribute('class'), 'ql-ui')) {
                $node->removeChild($child);

                continue;
            }

            self::cleanChildren($child);

            if (! isset(self::ALLOWED[$tag])) {
                self::unwrap($child);

                continue;
            }

            self::cleanAttributes($child, self::ALLOWED[$tag]);

            if ($tag === 'ol') {
                self::fixBulletList($child);
            }
        }
    }

    /**
     * @param  array<int, string>  $allowed
     */
    protected static function cleanAttributes(DOMElement $element, array $allowed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = trim($attribute->nodeValue ?? '');

            $keep = in_array($name, $allowed, true) && match ($name) {
                'href' => self::isSafeUrl($value),
                'target' => $value === '_blank',
                // Только классы выравнивания и отступов редактора.
                'class' => preg_match('/^(ql-[a-z0-9-]+)(\s+ql-[a-z0-9-]+)*$/', $value) === 1,
                'data-list' => in_array($value, ['bullet', 'ordered'], true),
                default => true,
            };

            if (! $keep) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($element->tagName === 'a') {
            if ($element->getAttribute('target') === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            } else {
                $element->removeAttribute('rel');
            }
        }
    }

    /**
     * Quill 2 хранит маркированный список как `<ol><li data-list="bullet">` —
     * на сайте он показался бы нумерованным.
     */
    protected static function fixBulletList(DOMElement $list): void
    {
        $items = array_filter(
            iterator_to_array($list->childNodes),
            fn (DOMNode $node) => $node instanceof DOMElement && $node->tagName === 'li',
        );

        if ($items === [] || array_filter($items, fn (DOMElement $li) => $li->getAttribute('data-list') !== 'bullet') !== []) {
            return;
        }

        $bullets = $list->ownerDocument->createElement('ul');

        while ($list->firstChild) {
            $bullets->appendChild($list->firstChild);
        }

        $list->parentNode->replaceChild($bullets, $list);
    }

    protected static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /**
     * Ссылка: http(s), почта, телефон или путь на этом сайте.
     */
    public static function isSafeUrl(string $url): bool
    {
        // Управляющие символы и пробелы внутри схемы — классический обход `javascript:`.
        $normalized = preg_replace('/[\x00-\x20]+/', '', $url) ?? '';

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $normalized, $match)) {
            return in_array(strtolower($match[0]), ['http:', 'https:', 'mailto:', 'tel:'], true);
        }

        // Относительные адреса: /page, #anchor, ?query, page.html — но не //evil.com.
        return ! str_starts_with($normalized, '//') && ! str_starts_with($normalized, '\\');
    }
}
