<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\News;
use App\Models\NewsImage;
use App\Models\NewsTranslation;
use App\Models\Redirect;

/**
 * Импорт новостей из файла экспорта WordPress (WXR — Инструменты → Экспорт).
 * Файл содержит тексты и ССЫЛКИ на картинки (не сами файлы), поэтому фото
 * берутся либо скачиванием по URL (если старый сайт ещё отдаёт медиа), либо из
 * локальной папки wp-content/uploads (опция uploadsDir).
 *
 * Двуязычность (Polylang): язык записи — из <category domain="language">, а
 * связь переводов — из общего <category domain="post_translations"> (одинаковый
 * nicename у переводов друг друга).
 */
final class WordPressWxrImporter
{
    /**
     * Разбирает WXR-XML в структуру (чистый метод, тестируемый).
     *
     * @return array{site:string, attachments:array<int,string>, posts:array<int,array<string,mixed>>}
     */
    public static function parse(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $rss = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($rss === false || !isset($rss->channel)) {
            return ['site' => '', 'attachments' => [], 'posts' => []];
        }
        $ns = $rss->getNamespaces(true);
        $wp = $ns['wp'] ?? 'http://wordpress.org/export/1.2/';
        $content = $ns['content'] ?? 'http://purl.org/rss/1.0/modules/content/';
        $excerpt = $ns['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/';

        $channel = $rss->channel;
        $site = '';
        foreach ($channel->children($wp) as $name => $val) {
            if ($name === 'base_site_url' || ($name === 'base_blog_url' && $site === '')) {
                $site = rtrim((string) $val, '/');
            }
        }

        $attachments = [];
        $posts = [];
        foreach ($channel->item as $item) {
            $w = $item->children($wp);
            $type = (string) $w->post_type;
            $id = (int) $w->post_id;

            if ($type === 'attachment') {
                $url = (string) $w->attachment_url;
                if ($id > 0 && $url !== '') {
                    $attachments[$id] = $url;
                }
                continue;
            }
            if ($type !== 'post') {
                continue;
            }

            $lang = '';
            $group = '';
            foreach ($item->category as $cat) {
                $domain = (string) $cat['domain'];
                if ($domain === 'language') {
                    $lang = (string) $cat['nicename'];
                } elseif ($domain === 'post_translations') {
                    $group = (string) $cat['nicename'];
                }
            }
            $thumbId = 0;
            foreach ($w->postmeta as $meta) {
                if ((string) $meta->meta_key === '_thumbnail_id') {
                    $thumbId = (int) $meta->meta_value;
                }
            }

            $posts[] = [
                'id' => $id,
                'title' => (string) $item->title,
                'slug' => (string) $w->post_name,
                'link' => (string) $item->link,
                'date' => (string) $w->post_date,
                'status' => (string) $w->status,
                'content' => (string) $item->children($content)->encoded,
                'excerpt' => (string) $item->children($excerpt)->encoded,
                'lang' => $lang,
                'group' => $group,
                'thumb_id' => $thumbId,
            ];
        }

        return ['site' => $site, 'attachments' => $attachments, 'posts' => $posts];
    }

    /**
     * @param array{status?:string,authorId?:?int,langs?:array<string,string>,uploadsDir?:?string,limit?:int,dryRun?:bool} $opts
     * @return array{imported:int,skipped:int,images:int,redirects:int,translations:int,errors:array<int,string>}
     */
    public static function importFile(string $path, array $opts = []): array
    {
        $out = ['imported' => 0, 'skipped' => 0, 'images' => 0, 'redirects' => 0, 'translations' => 0, 'errors' => []];
        if (!is_file($path)) {
            $out['errors'][] = 'Файл не найден: ' . $path;
            return $out;
        }
        $data = self::parse((string) file_get_contents($path));
        if ($data['posts'] === []) {
            $out['errors'][] = 'В файле не найдено записей типа «post» (проверьте, что это экспорт WordPress).';
            return $out;
        }

        $status = ($opts['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $authorId = $opts['authorId'] ?? null;
        $uploadsDir = $opts['uploadsDir'] ?? null;
        $limit = (int) ($opts['limit'] ?? 0);
        $dryRun = !empty($opts['dryRun']);
        /** @var array<string,string> $langs */
        $langs = (array) ($opts['langs'] ?? []);
        $primaryWp = $langs !== [] ? (string) array_key_first($langs) : '';
        $site = $data['site'];
        $att = $data['attachments'];

        // Группируем переводы по общему nicename (Polylang). Без группы — соло.
        $groups = [];
        foreach ($data['posts'] as $p) {
            $key = $p['group'] !== '' ? 'g:' . $p['group'] : 'p:' . $p['id'];
            $groups[$key][] = $p;
        }

        foreach ($groups as $group) {
            if ($limit > 0 && $out['imported'] >= $limit) {
                break;
            }
            // Основная запись: язык primaryWp, иначе первая в группе.
            $primary = $group[0];
            if ($primaryWp !== '') {
                foreach ($group as $p) {
                    if ($p['lang'] === $primaryWp) {
                        $primary = $p;
                        break;
                    }
                }
            }
            $slug = $primary['slug'] !== '' ? $primary['slug'] : Slug::make($primary['title']);
            if ($slug === '') {
                $out['skipped']++;
                continue;
            }
            try {
                if (News::slugExists($slug)) {
                    $out['skipped']++;
                    continue;
                }
                if ($dryRun) {
                    $out['imported']++;
                    continue;
                }

                $featuredUrl = $primary['thumb_id'] > 0 ? ($att[$primary['thumb_id']] ?? '') : '';
                [$content, $gallery] = self::transferBody((string) $primary['content'], $site, $authorId, $uploadsDir, $out);
                $cover = '';
                if ($featuredUrl !== '') {
                    $cover = (string) (WordPressImporter::importImage($featuredUrl, $authorId, $uploadsDir) ?? '');
                    if ($cover !== '' && !in_array($cover, $gallery, true)) {
                        $out['images']++;
                    }
                }
                if ($cover === '' && $gallery !== []) {
                    $cover = $gallery[0];
                }

                $newsId = News::create([
                    'title' => trim(html_entity_decode(strip_tags((string) $primary['title']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                    'slug' => $slug,
                    'excerpt' => mb_substr(trim(html_entity_decode(strip_tags((string) $primary['excerpt']), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 300),
                    'content' => $content,
                    'image' => $cover,
                    'status' => $status,
                    'published_at' => self::date((string) $primary['date']),
                    'author_id' => $authorId,
                ]);
                $out['imported']++;
                foreach ($gallery as $i => $p) {
                    NewsImage::create($newsId, $p, null, $i);
                }
                $from = (string) parse_url((string) $primary['link'], PHP_URL_PATH);
                if ($from !== '' && trim($from, '/') !== '' && $from !== '/news/' . $slug && Redirect::create($from, '/news/' . $slug, 301)) {
                    $out['redirects']++;
                }

                // Остальные языки группы — переводами.
                foreach ($group as $p) {
                    if ($p['id'] === $primary['id']) {
                        continue;
                    }
                    $artCode = $langs[$p['lang']] ?? '';
                    if ($artCode === '') {
                        continue;
                    }
                    [$tContent] = self::transferBody((string) $p['content'], $site, $authorId, $uploadsDir, $out);
                    NewsTranslation::upsert($newsId, $artCode, [
                        'title' => trim(html_entity_decode(strip_tags((string) $p['title']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                        'excerpt' => mb_substr(trim(html_entity_decode(strip_tags((string) $p['excerpt']), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 300),
                        'content' => $tContent,
                    ]);
                    $out['translations']++;
                }
            } catch (\Throwable $e) {
                $out['errors'][] = 'Запись "' . $slug . '": ' . $e->getMessage();
            }
        }

        return $out;
    }

    /**
     * Переносит картинки тела и возвращает [переписанный HTML, список новых URL].
     *
     * @param array<string,mixed> $out
     * @return array{0:string,1:array<int,string>}
     */
    private static function transferBody(string $html, string $site, ?int $authorId, ?string $uploadsDir, array &$out): array
    {
        $map = [];
        foreach (WordPressImporter::extractImageUrls($html) as $src) {
            $abs = WordPressImporter::normalizeImageUrl(WordPressImporter::absoluteUrl($src, $site !== '' ? $site : ''));
            $newUrl = WordPressImporter::importImage($abs, $authorId, $uploadsDir);
            if ($newUrl !== null) {
                $map[$src] = $newUrl;
                $out['images']++;
            }
        }

        $clean = WordPressImporter::stripResponsiveAttrs(WordPressImporter::rewriteImages($html, $map));

        return [$clean, array_values($map)];
    }

    private static function date(string $d): string
    {
        $ts = $d !== '' && $d !== '0000-00-00 00:00:00' ? strtotime($d) : false;

        return date('Y-m-d H:i:s', $ts !== false ? $ts : time());
    }
}
