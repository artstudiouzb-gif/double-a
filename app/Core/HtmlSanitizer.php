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

    /**
     * Узкий текстовый профиль для кастомных полей конструктора контента:
     * только разметка текста (без div/img/таблиц/style/class).
     */
    private const TEXT_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'a',
    ];

    private const TEXT_ATTRS = [
        '*' => [],
        'a' => ['href', 'target', 'rel'],
    ];

    /**
     * Очистка контента кастомных полей (типы контента, этап 16.4): остаётся
     * только безопасная разметка текста; script/iframe, обработчики on*,
     * javascript:-ссылки и все атрибуты вне allowlist вырезаются.
     */
    public static function sanitizeText(string $html): string
    {
        return self::sanitize($html, self::TEXT_TAGS, self::TEXT_ATTRS);
    }

    /**
     * @param array<int, string>|null $allowedTags
     * @param array<string, array<int, string>>|null $allowedAttrs
     */
    public static function sanitize(string $html, ?array $allowedTags = null, ?array $allowedAttrs = null): string
    {
        $allowedTags ??= self::ALLOWED_TAGS;
        $allowedAttrs ??= self::ALLOWED_ATTRS;
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

        self::cleanNode($root, $allowedTags, $allowedAttrs);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * @param array<int, string> $allowedTags
     * @param array<string, array<int, string>> $allowedAttrs
     */
    private static function cleanNode(\DOMNode $node, array $allowedTags, array $allowedAttrs): void
    {
        // Обходим копию списка детей — узлы будут удаляться на месте.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->nodeName);

                if (!in_array($tag, $allowedTags, true)) {
                    // script/style удаляем ЦЕЛИКОМ — их содержимое это код,
                    // а не контент. Прочие неразрешённые теги (iframe и т.п.)
                    // разворачиваем, сохраняя видимый текст.
                    if (in_array($tag, ['script', 'style'], true)) {
                        $child->parentNode?->removeChild($child);
                    } else {
                        self::cleanNode($child, $allowedTags, $allowedAttrs);
                        self::unwrap($child);
                    }
                    continue;
                }

                self::cleanAttributes($child, $tag, $allowedAttrs);
                self::cleanNode($child, $allowedTags, $allowedAttrs);
            } elseif ($child instanceof \DOMComment) {
                $child->parentNode?->removeChild($child);
            }
            // Текстовые узлы оставляем как есть — saveHTML их экранирует.
        }
    }

    /**
     * @param array<string, array<int, string>> $allowedAttrs
     */
    private static function cleanAttributes(\DOMElement $el, string $tag, array $allowedAttrs): void
    {
        $allowed = array_merge(
            $allowedAttrs['*'] ?? [],
            $allowedAttrs[$tag] ?? []
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
