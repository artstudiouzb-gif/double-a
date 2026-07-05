<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Строгий allowlist-санитайзер HTML на нативном DOMDocument (без сторонних
 * библиотек). Используется, когда HTML вводит роль `editor` (блок типа `html`):
 * разрешён только безопасный форматирующий разметочный набор, любые скрипты,
 * обработчики on*, javascript:/data:-URI и опасные теги вырезаются.
 *
 * Супер-администратор вводит HTML без санитизации (доверенный источник).
 */
final class HtmlSanitizer
{
    /** Разрешённые теги (форматирование, ссылки, изображения, таблицы, списки). */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'span', 'div', 'section', 'article',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'small', 'sub', 'sup', 'mark',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    ];

    /** Разрешённые атрибуты по тегам. */
    private const ALLOWED_ATTRS = [
        '*' => ['class', 'id', 'title', 'style'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        // Оборачиваем во wrapper и заставляем разбирать как UTF-8; LIBXML_NONET
        // запрещает сетевые обращения (защита от XXE/SSRF при разборе).
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            // Не удалось разобрать — отдаём только экранированный текст.
            return htmlspecialchars(strip_tags($html), ENT_QUOTES);
        }

        $root = $dom->getElementById('__root__');
        if ($root === null) {
            return '';
        }

        self::cleanNode($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function cleanNode(\DOMNode $node): void
    {
        // Обходим копию списка детей — узлы будут удаляться на месте.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->nodeName);

                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Неразрешённый тег: разворачиваем его содержимое наружу
                    // (сохраняем текст), сам тег удаляем.
                    self::unwrap($child);
                    continue;
                }

                self::cleanAttributes($child, $tag);
                self::cleanNode($child);
            } elseif ($child instanceof \DOMComment) {
                $child->parentNode?->removeChild($child);
            }
            // Текстовые узлы оставляем как есть — saveHTML их экранирует.
        }
    }

    private static function cleanAttributes(\DOMElement $el, string $tag): void
    {
        $allowed = array_merge(
            self::ALLOWED_ATTRS['*'],
            self::ALLOWED_ATTRS[$tag] ?? []
        );

        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = $attr->nodeValue ?? '';

            if (!in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }

            $normalized = strtolower(preg_replace('/\s+/', '', $value) ?? '');

            // URL-атрибуты: только http/https/mailto/tel/относительные и data:image.
            if (in_array($name, ['href', 'src'], true)) {
                if (str_starts_with($normalized, 'javascript:')
                    || str_starts_with($normalized, 'vbscript:')
                    || (str_starts_with($normalized, 'data:') && !str_starts_with($normalized, 'data:image/'))) {
                    $el->removeAttribute($attr->nodeName);
                    continue;
                }
            }

            // style: вырезаем выражения и внешние ссылки.
            if ($name === 'style'
                && (stripos($value, 'javascript:') !== false
                    || stripos($value, 'expression') !== false
                    || stripos($value, 'url(') !== false)) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }
        }

        // Внешние ссылки делаем безопасными.
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /** Заменяет элемент его дочерними узлами (сохраняя текстовое содержимое). */
    private static function unwrap(\DOMElement $el): void
    {
        $parent = $el->parentNode;
        if ($parent === null) {
            return;
        }
        while ($el->firstChild !== null) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }
}
