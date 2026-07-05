<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Собирает зависимости (JS/CSS) блоков страницы и выводит каждую строго один
 * раз внизу страницы. Если на странице несколько одинаковых блоков (например,
 * три слайдера), их общий скрипт подключается однократно.
 */
final class AssetCollector
{
    /** @var array<string, bool> */
    private static array $js = [];

    /** @var array<string, bool> */
    private static array $css = [];

    /** Известные ассеты блоков: ключ -> путь к файлу. */
    private const JS_MAP = [
        'slider' => '/assets/js/blocks/slider.js',
        'gallery' => '/assets/js/blocks/gallery.js',
        'news' => '/assets/js/news.js',
    ];

    public static function requireJs(string $key): void
    {
        if (isset(self::JS_MAP[$key])) {
            self::$js[$key] = true;
        }
    }

    public static function requireCss(string $key, string $href): void
    {
        self::$css[$key] = $href;
    }

    public static function reset(): void
    {
        self::$js = [];
        self::$css = [];
    }

    public static function renderScripts(): string
    {
        $html = '';
        foreach (array_keys(self::$js) as $key) {
            $src = self::JS_MAP[$key];
            $html .= '<script src="' . htmlspecialchars($src, ENT_QUOTES) . '" defer></script>' . "\n";
        }

        return $html;
    }

    public static function renderStyles(): string
    {
        $html = '';
        foreach (self::$css as $href) {
            $html .= '<link rel="stylesheet" href="' . htmlspecialchars((string) $href, ENT_QUOTES) . '">' . "\n";
        }

        return $html;
    }
}
