<?php

namespace Nexor\Cms\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Очистка загруженного SVG.
 *
 * SVG — это разметка: `<script>`, `onload` или ссылка `javascript:` в нём
 * выполнятся на домене сайта, стоит открыть файл по прямому адресу. Поэтому
 * загруженный файл пересобирается: исполняемое вырезается, рисунок остаётся.
 * То, что не разбирается как SVG, не принимается вовсе.
 */
class SvgSanitizer
{
    /** Элементы, которым в картинке делать нечего. */
    protected const FORBIDDEN = [
        'script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video',
        'link', 'meta', 'handler', 'listener',
    ];

    /** Анимации: опасны, только когда меняют адрес ссылки. */
    protected const ANIMATIONS = ['set', 'animate', 'animatetransform', 'animatemotion'];

    /** Атрибуты с адресом. */
    protected const LINKS = ['href', 'xlink:href', 'src', 'action', 'formaction'];

    /**
     * Чистый SVG или null, если это не SVG.
     */
    public static function clean(string $svg): ?string
    {
        $svg = trim($svg);

        // Сущности из DOCTYPE — это и чтение чужих файлов, и «миллиард смеха».
        if ($svg === '' || stripos($svg, '<!ENTITY') !== false) {
            return null;
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // Без LIBXML_NOENT: сущности не подставляются. Без сети: внешнее не тянется.
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->documentElement;

        if (! $loaded || ! $root instanceof DOMElement || strtolower($root->localName) !== 'svg') {
            return null;
        }

        if ($dom->doctype !== null) {
            $dom->removeChild($dom->doctype);
        }

        self::scrub($root);

        return (string) $dom->saveXML($root);
    }

    /**
     * Обходит дерево: убирает лишние элементы и опасные атрибуты.
     */
    protected static function scrub(DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                self::scrubText($child);

                continue;
            }

            $name = strtolower($child->localName);

            if (in_array($name, self::FORBIDDEN, true)
                || (in_array($name, self::ANIMATIONS, true) && stripos((string) $child->getAttribute('attributeName'), 'href') !== false)) {
                $element->removeChild($child);

                continue;
            }

            self::scrub($child);
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            if ($attribute instanceof DOMAttr && self::dangerous($element, $attribute)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    /**
     * Стили внутри <style>: без подключения чужих файлов и без скриптовых адресов.
     */
    protected static function scrubText(DOMNode $node): void
    {
        if ($node->parentNode instanceof DOMElement && strtolower($node->parentNode->localName) === 'style') {
            $node->nodeValue = (string) preg_replace(
                ['/@import[^;]*;?/i', '/expression\s*\(/i', '/javascript\s*:/i'],
                '',
                (string) $node->nodeValue,
            );
        }
    }

    protected static function dangerous(DOMElement $element, DOMAttr $attribute): bool
    {
        $name = strtolower($attribute->nodeName);
        $value = trim((string) $attribute->value);

        // onload, onclick и прочие обработчики.
        if (str_starts_with($name, 'on')) {
            return true;
        }

        if ($name === 'style') {
            return preg_match('/javascript\s*:|expression\s*\(|@import/i', $value) === 1;
        }

        if (! in_array($name, self::LINKS, true)) {
            return false;
        }

        // <use> берёт рисунок только из этого же файла.
        if (strtolower($element->localName) === 'use') {
            return ! str_starts_with($value, '#');
        }

        // Пробелы и управляющие символы внутри схемы браузер пропускает.
        $compact = (string) preg_replace('/[\s\x00-\x1f]+/', '', strtolower($value));

        if (str_starts_with($compact, 'data:')) {
            return preg_match('#^data:image/(png|jpe?g|gif|webp);#', $compact) !== 1;
        }

        return preg_match('#^[a-z][a-z0-9+.\-]*:#', $compact) === 1
            && preg_match('#^https?:#', $compact) !== 1;
    }
}
