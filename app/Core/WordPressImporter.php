<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\News;
use App\Models\NewsImage;
use App\Models\NewsTranslation;
use App\Models\Redirect;

/**
 * Импорт новостей из WordPress через REST API (/wp-json/wp/v2/posts) с
 * переносом фотографий. Чистые методы (mapPost/extractImageUrls/rewriteImages)
 * покрыты тестами; importAll — оркестрация с сетью и БД.
 *
 * По умолчанию импортирует как ЧЕРНОВИКИ (безопасно: контент-редактор
 * просматривает перед публикацией). Идемпотентно: посты с уже существующим
 * slug пропускаются, повторный запуск не плодит дубли.
 */
final class WordPressImporter
{
    /** Разрешённые расширения картинок для переноса. */
    private const IMG_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Кэш перенесённых картинок за один запуск: исходный URL => новый URL. */
    private static array $imageCache = [];

    /**
     * @param array{limit?:int,perPage?:int,status?:string,authorId?:?int,dryRun?:bool,langs?:array<string,string>} $opts
     *   langs — карта «код языка WP => код языка ArtStudio», первый = основной
     *   (в него пишется базовая строка новости, остальные — переводы). Пусто —
     *   одноязычный импорт.
     * @return array{imported:int,skipped:int,images:int,redirects:int,translations:int,errors:array<int,string>}
     */
    public static function importAll(string $baseUrl, array $opts = []): array
    {
        $base = rtrim($baseUrl, '/');
        $perPage = max(1, min(100, (int) ($opts['perPage'] ?? 20)));
        $limit = (int) ($opts['limit'] ?? 0); // 0 = все
        $status = ($opts['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $authorId = $opts['authorId'] ?? null;
        $dryRun = !empty($opts['dryRun']);
        /** @var array<string,string> $langs */
        $langs = (array) ($opts['langs'] ?? []);
        $primaryWp = $langs !== [] ? (string) array_key_first($langs) : '';

        self::$imageCache = [];
        $out = ['imported' => 0, 'skipped' => 0, 'images' => 0, 'redirects' => 0, 'translations' => 0, 'errors' => []];
        $page = 1;

        while (true) {
            $url = $base . '/wp-json/wp/v2/posts?_embed=1&per_page=' . $perPage . '&page=' . $page
                . ($primaryWp !== '' ? '&lang=' . rawurlencode($primaryWp) : '');
            $resp = Http::request('GET', $url, '', ['Accept: application/json']);
            if (($resp['status'] ?? 0) !== 200) {
                if ($page === 1) {
                    $out['errors'][] = 'REST недоступен (HTTP ' . ($resp['status'] ?? 0) . '): ' . $url;
                }
                break;
            }
            $posts = json_decode((string) $resp['body'], true);
            if (!is_array($posts) || $posts === []) {
                break;
            }

            foreach ($posts as $post) {
                if ($limit > 0 && $out['imported'] >= $limit) {
                    return $out;
                }
                try {
                    $mapped = self::mapPost($post);
                    if ($mapped['slug'] === '' || News::slugExists($mapped['slug'])) {
                        $out['skipped']++;
                        continue;
                    }
                    if ($dryRun) {
                        $out['imported']++;
                        continue;
                    }

                    $newsId = self::createFromPost($mapped, $status, $authorId, $base, $out);
                    $out['imported']++;

                    // Переводы (Polylang/WPML): post['translations'] = {код: id}.
                    $trans = is_array($post['translations'] ?? null) ? $post['translations'] : [];
                    foreach ($langs as $wpCode => $artCode) {
                        if ($wpCode === $primaryWp) {
                            continue;
                        }
                        $tid = (int) ($trans[$wpCode] ?? 0);
                        if ($tid <= 0) {
                            continue;
                        }
                        $tPost = self::fetchPost($base, $tid);
                        if ($tPost === null) {
                            continue;
                        }
                        $tm = self::mapPost($tPost);
                        $tContent = self::rewriteImages($tm['content'], self::transferAll($tm['content'], $base, $authorId, $out));
                        NewsTranslation::upsert($newsId, (string) $artCode, [
                            'title' => $tm['title'],
                            'excerpt' => $tm['excerpt'],
                            'content' => $tContent,
                        ]);
                        $out['translations']++;
                    }
                } catch (\Throwable $e) {
                    $out['errors'][] = 'Пост "' . (($post['slug'] ?? '') ?: '?') . '": ' . $e->getMessage();
                }
            }

            $page++;
            if (count($posts) < $perPage) {
                break; // последняя страница
            }
        }

        return $out;
    }

    /**
     * Создаёт базовую новость из размеченного поста: переносит картинки тела и
     * обложку, пишет News, галерею и редирект. Возвращает id новости.
     *
     * @param array{title:string,slug:string,excerpt:string,content:string,published_at:string,featured_url:string,link:string} $mapped
     * @param array<string,mixed> $out
     */
    private static function createFromPost(array $mapped, string $status, ?int $authorId, string $base, array &$out): int
    {
        $map = self::transferAll($mapped['content'], $base, $authorId, $out);
        $content = self::rewriteImages($mapped['content'], $map);
        $galleryPaths = array_values($map);

        $cover = '';
        if ($mapped['featured_url'] !== '') {
            $cover = (string) (self::transferImage($mapped['featured_url'], $authorId) ?? '');
            if ($cover !== '' && !in_array($cover, $galleryPaths, true)) {
                $out['images']++;
            }
        }
        if ($cover === '' && $galleryPaths !== []) {
            $cover = $galleryPaths[0];
        }

        $newsId = News::create([
            'title' => $mapped['title'],
            'slug' => $mapped['slug'],
            'excerpt' => $mapped['excerpt'],
            'content' => $content,
            'image' => $cover,
            'status' => $status,
            'published_at' => $mapped['published_at'],
            'author_id' => $authorId,
        ]);

        foreach ($galleryPaths as $i => $p) {
            NewsImage::create($newsId, $p, null, $i);
        }

        $from = (string) parse_url($mapped['link'], PHP_URL_PATH);
        $to = '/news/' . $mapped['slug'];
        if ($from !== '' && trim($from, '/') !== '' && $from !== $to && Redirect::create($from, $to, 301)) {
            $out['redirects']++;
        }

        return $newsId;
    }

    /**
     * Переносит все картинки тела статьи (только с исходного домена) и возвращает
     * карту [старый src => новый URL] для перезаписи.
     *
     * @param array<string,mixed> $out
     * @return array<string,string>
     */
    private static function transferAll(string $html, string $base, ?int $authorId, array &$out): array
    {
        $map = [];
        foreach (self::extractImageUrls($html) as $src) {
            $abs = self::absoluteUrl($src, $base);
            if (!str_starts_with($abs, $base)) {
                continue; // внешние картинки не тянем
            }
            $cached = isset(self::$imageCache[$abs]);
            $newUrl = self::transferImage($abs, $authorId);
            if ($newUrl !== null) {
                $map[$src] = $newUrl;
                if (!$cached) {
                    $out['images']++;
                }
            }
        }

        return $map;
    }

    /** Запрашивает один пост по id (для переводов). */
    private static function fetchPost(string $base, int $id): ?array
    {
        $resp = Http::request('GET', $base . '/wp-json/wp/v2/posts/' . $id . '?_embed=1', '', ['Accept: application/json']);
        if (($resp['status'] ?? 0) !== 200) {
            return null;
        }
        $post = json_decode((string) $resp['body'], true);

        return is_array($post) ? $post : null;
    }

    /**
     * Преобразует пост WP REST в поля новости ArtStudio.
     *
     * @param array<string,mixed> $post
     * @return array{title:string,slug:string,excerpt:string,content:string,published_at:string,featured_url:string,link:string}
     */
    public static function mapPost(array $post): array
    {
        $rendered = static fn ($v): string => is_array($v) ? (string) ($v['rendered'] ?? '') : (string) $v;

        $title = trim(html_entity_decode(strip_tags($rendered($post['title'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $slug = trim((string) ($post['slug'] ?? ''));
        if ($slug === '' && $title !== '') {
            $slug = Slug::make($title);
        }
        $content = $rendered($post['content'] ?? '');
        $excerpt = trim(html_entity_decode(strip_tags($rendered($post['excerpt'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $excerpt = mb_substr(preg_replace('/\s+/', ' ', $excerpt) ?? '', 0, 300);

        $date = (string) ($post['date'] ?? $post['date_gmt'] ?? '');
        $ts = $date !== '' ? strtotime($date) : false;
        $publishedAt = date('Y-m-d H:i:s', $ts !== false ? $ts : time());

        $featured = '';
        $media = $post['_embedded']['wp:featuredmedia'][0]['source_url'] ?? '';
        if (is_string($media)) {
            $featured = $media;
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'content' => $content,
            'published_at' => $publishedAt,
            'featured_url' => $featured,
            'link' => (string) ($post['link'] ?? ''),
        ];
    }

    /**
     * Все значения src из <img> в HTML.
     *
     * @return array<int,string>
     */
    public static function extractImageUrls(string $html): array
    {
        if (!preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            return [];
        }

        return array_values(array_unique($m[1]));
    }

    /**
     * Заменяет старые URL картинок на новые по карте [старый => новый].
     *
     * @param array<string,string> $map
     */
    public static function rewriteImages(string $html, array $map): string
    {
        if ($map === []) {
            return $html;
        }

        return strtr($html, $map);
    }

    /** Абсолютизирует ссылку картинки (protocol-relative / относительная). */
    public static function absoluteUrl(string $src, string $base): string
    {
        if (str_starts_with($src, '//')) {
            $scheme = (string) (parse_url($base, PHP_URL_SCHEME) ?: 'https');
            return $scheme . ':' . $src;
        }
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        if ($src !== '' && $src[0] === '/') {
            return $base . $src;
        }

        return $base . '/' . ltrim($src, '/');
    }

    /**
     * Скачивает картинку и сохраняет в медиабиблиотеку через Uploader.
     * Возвращает публичный URL или null при ошибке.
     */
    private static function transferImage(string $url, ?int $uploadedBy): ?string
    {
        if (isset(self::$imageCache[$url])) {
            return self::$imageCache[$url];
        }
        try {
            $tmp = tempnam(sys_get_temp_dir(), 'wpimg_');
            if ($tmp === false) {
                return null;
            }
            $ok = self::download($url, $tmp);
            if (!$ok) {
                @unlink($tmp);
                return null;
            }
            $ext = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (!in_array($ext, self::IMG_EXT, true)) {
                $ext = self::extFromMime((string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp));
            }
            if ($ext === null) {
                @unlink($tmp);
                return null;
            }
            $named = $tmp . '.' . $ext;
            @rename($tmp, $named);
            $file = Uploader::storeFromPath($named, 'wp-' . basename((string) parse_url($url, PHP_URL_PATH)) . '.' . $ext, (int) filesize($named), 'public', $uploadedBy, false);
            @unlink($named);

            $publicUrl = \App\Models\FileEntry::publicUrl($file);
            self::$imageCache[$url] = $publicUrl;

            return $publicUrl;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Скачивание с follow-редиректов (WP-медиа иногда редиректит). */
    private static function download(string $url, string $dest): bool
    {
        if (!function_exists('curl_init')) {
            $data = @file_get_contents($url);
            return $data !== false && file_put_contents($dest, $data) !== false;
        }
        $fh = fopen($dest, 'wb');
        if ($fh === false) {
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => 'ArtStudio-WP-Import/1.0',
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fh);

        return $ok !== false && $status >= 200 && $status < 300 && filesize($dest) > 0;
    }

    private static function extFromMime(string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };
    }
}
