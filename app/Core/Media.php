<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Вывод адаптивных изображений через <picture>. Если файл — локальная загрузка,
 * для которой Uploader::optimizeImage сгенерировал WebP-разрешения
 * (name-800.webp / name-1600.webp / name.webp), они подставляются в srcset.
 * Внешние URL и файлы без вариантов отдаются обычным <img> (graceful fallback).
 *
 * Фокальная точка (в %) кладётся в object-position — при object-fit: cover
 * ключевой объект остаётся в кадре на любых пропорциях.
 */
final class Media
{
    public static function picture(
        ?string $url,
        string $alt = '',
        ?int $focalX = null,
        ?int $focalY = null,
        string $imgClass = '',
        bool $lazy = true
    ): string {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $altAttr = htmlspecialchars($alt, ENT_QUOTES);
        $classAttr = $imgClass !== '' ? ' class="' . htmlspecialchars($imgClass, ENT_QUOTES) . '"' : '';
        $loadingAttr = $lazy ? ' loading="lazy" decoding="async"' : '';
        $styleAttr = '';
        if ($focalX !== null && $focalY !== null) {
            $fx = max(0, min(100, $focalX));
            $fy = max(0, min(100, $focalY));
            $styleAttr = ' style="object-position:' . $fx . '% ' . $fy . '%"';
        }

        $img = '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . $altAttr . '"'
            . $classAttr . $loadingAttr . $styleAttr . '>';

        $variants = self::webpVariants($url);
        if ($variants === null) {
            return $img;
        }

        $srcset = [];
        if ($variants['w800'] !== null) {
            $srcset[] = htmlspecialchars($variants['w800'], ENT_QUOTES) . ' 800w';
        }
        if ($variants['w1600'] !== null) {
            $srcset[] = htmlspecialchars($variants['w1600'], ENT_QUOTES) . ' 1600w';
        }
        if ($variants['full'] !== null) {
            $srcset[] = htmlspecialchars($variants['full'], ENT_QUOTES) . ' 2000w';
        }
        if ($srcset === []) {
            return $img;
        }

        return '<picture>'
            . '<source type="image/webp" srcset="' . implode(', ', $srcset) . '" '
            . 'sizes="(max-width: 800px) 100vw, 800px">'
            . $img
            . '</picture>';
    }

    /**
     * Возвращает пути к существующим WebP-вариантам для локального URL загрузки,
     * либо null, если это не локальная загрузка / вариантов нет.
     *
     * @return array{full: ?string, w1600: ?string, w800: ?string}|null
     */
    private static function webpVariants(string $url): ?array
    {
        $urlPrefix = rtrim((string) Config::get('paths.public_uploads_url', '/uploads/public'), '/');
        $diskBase = rtrim((string) Config::get('paths.public_uploads', ''), '/');
        if ($diskBase === '' || !str_starts_with($url, $urlPrefix . '/')) {
            return null;
        }

        // Отбрасываем querystring/anchor.
        $clean = preg_replace('/[?#].*$/', '', $url) ?? $url;
        $relative = substr($clean, strlen($urlPrefix));           // /abc.jpg
        $relNoExt = preg_replace('/\.[^.\/]+$/', '', $relative) ?? $relative;

        $map = [
            'full' => $relNoExt . '.webp',
            'w1600' => $relNoExt . '-1600.webp',
            'w800' => $relNoExt . '-800.webp',
        ];

        $result = ['full' => null, 'w1600' => null, 'w800' => null];
        $found = false;
        foreach ($map as $key => $rel) {
            if (is_file($diskBase . $rel)) {
                $result[$key] = $urlPrefix . $rel;
                $found = true;
            }
        }

        return $found ? $result : null;
    }
}
