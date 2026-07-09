<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Config;
use App\Core\Database;
use App\Models\Language;

final class SitemapController
{
    public function xml(): void
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $languages = Language::active();
        $defaultCode = Language::defaultCode();

        $prefix = static function (string $code) use ($defaultCode): string {
            return $code === $defaultCode ? '' : '/' . $code;
        };

        $urls = [];

        // Главная + постоянные разделы (новости, календарь, альбомы) на каждом языке.
        foreach ($languages as $lang) {
            $p = $prefix((string) $lang['code']);
            $urls[] = ['loc' => $base . ($p === '' ? '/' : $p), 'priority' => '1.0'];
            $urls[] = ['loc' => $base . $p . '/news', 'priority' => '0.7'];
            $urls[] = ['loc' => $base . $p . '/calendar', 'priority' => '0.6'];
            $urls[] = ['loc' => $base . $p . '/albums', 'priority' => '0.5'];
        }

        // Опубликованные страницы (не главные, не удалённые).
        $pages = Database::pdo()->query(
            "SELECT slug, updated_at FROM pages WHERE status = 'published' AND is_home = 0 AND deleted_at IS NULL"
        )->fetchAll();
        foreach ($pages as $page) {
            foreach ($languages as $lang) {
                $urls[] = [
                    'loc' => $base . $prefix((string) $lang['code']) . '/' . $page['slug'],
                    'lastmod' => substr((string) $page['updated_at'], 0, 10),
                    'priority' => '0.8',
                ];
            }
        }

        // Опубликованные новости.
        $news = Database::pdo()->query(
            "SELECT slug, updated_at FROM news WHERE status = 'published' AND published_at <= NOW() AND deleted_at IS NULL"
        )->fetchAll();
        foreach ($news as $item) {
            foreach ($languages as $lang) {
                $urls[] = [
                    'loc' => $base . $prefix((string) $lang['code']) . '/news/' . $item['slug'],
                    'lastmod' => substr((string) $item['updated_at'], 0, 10),
                    'priority' => '0.6',
                ];
            }
        }

        // Публичные пользовательские типы контента: страница раздела + записи.
        $types = Database::pdo()->query(
            'SELECT id, slug FROM content_types WHERE is_public = 1'
        )->fetchAll();
        foreach ($types as $type) {
            foreach ($languages as $lang) {
                $urls[] = [
                    'loc' => $base . $prefix((string) $lang['code']) . '/catalog/' . $type['slug'],
                    'priority' => '0.7',
                ];
            }
            $stmt = Database::pdo()->prepare(
                "SELECT slug, updated_at FROM content_entries
                 WHERE type_id = :t AND status = 'published' AND deleted_at IS NULL"
            );
            $stmt->execute([':t' => (int) $type['id']]);
            foreach ($stmt->fetchAll() as $entry) {
                foreach ($languages as $lang) {
                    $urls[] = [
                        'loc' => $base . $prefix((string) $lang['code']) . '/catalog/' . $type['slug'] . '/' . $entry['slug'],
                        'lastmod' => substr((string) $entry['updated_at'], 0, 10),
                        'priority' => '0.6',
                    ];
                }
            }
        }

        // Опубликованные фотоальбомы.
        try {
            $albums = Database::pdo()->query(
                'SELECT slug, created_at FROM photo_albums WHERE is_published = 1'
            )->fetchAll();
            foreach ($albums as $album) {
                foreach ($languages as $lang) {
                    $urls[] = [
                        'loc' => $base . $prefix((string) $lang['code']) . '/albums/' . $album['slug'],
                        'lastmod' => substr((string) $album['created_at'], 0, 10),
                        'priority' => '0.5',
                    ];
                }
            }
        } catch (\Throwable) {
            // Таблицы может не быть до применения миграции — sitemap не ломаем.
        }

        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            echo '  <url>' . "\n";
            echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . '</loc>' . "\n";
            if (!empty($url['lastmod'])) {
                echo '    <lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1) . '</lastmod>' . "\n";
            }
            echo '    <priority>' . $url['priority'] . '</priority>' . "\n";
            echo '  </url>' . "\n";
        }
        echo '</urlset>' . "\n";
        exit;
    }

    public function robots(): void
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');

        header('Content-Type: text/plain; charset=UTF-8');
        echo "User-agent: *\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /install\n";
        echo "Disallow: /repo\n";
        echo "Disallow: /search\n";
        echo "Disallow: /download.php\n";
        echo "Allow: /\n\n";
        echo 'Sitemap: ' . $base . "/sitemap.xml\n";
        exit;
    }
}
