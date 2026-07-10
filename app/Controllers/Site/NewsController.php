<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Config;
use App\Core\Locale;
use App\Core\View;
use App\Models\News;
use App\Models\NewsImage;
use App\Models\Setting;

final class NewsController
{
    public function index(): void
    {
        $lang = Locale::current();
        $perPage = 13; // 1 крупная + 12 в сетке
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = News::publishedCount();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        View::render('site/news_index', [
            'items' => News::published($perPage, ($page - 1) * $perPage, $lang),
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    /**
     * RSS 2.0 лента последних новостей (стандарт для гос-сайтов и агрегаторов).
     */
    public function feed(): void
    {
        $lang = Locale::current();
        $items = News::published(30, 0, $lang);
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $siteName = (string) Setting::get('site_name', 'ArtStudio');
        $selfUrl = $base . Locale::url('news/rss.xml', $lang);

        header('Content-Type: application/rss+xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo '<channel>' . "\n";
        echo '  <title>' . htmlspecialchars($siteName . ' — Новости', ENT_XML1) . '</title>' . "\n";
        echo '  <link>' . htmlspecialchars($base . Locale::url('news', $lang), ENT_XML1) . '</link>' . "\n";
        echo '  <description>' . htmlspecialchars((string) Setting::get('default_meta_description', 'Новости'), ENT_XML1) . '</description>' . "\n";
        echo '  <language>' . htmlspecialchars($lang, ENT_XML1) . '</language>' . "\n";
        echo '  <atom:link href="' . htmlspecialchars($selfUrl, ENT_XML1) . '" rel="self" type="application/rss+xml"/>' . "\n";
        foreach ($items as $item) {
            $link = $base . Locale::url('news/' . $item['slug'], $lang);
            $pub = $item['published_at'] ? date(DATE_RSS, strtotime((string) $item['published_at'])) : date(DATE_RSS);
            echo '  <item>' . "\n";
            echo '    <title>' . htmlspecialchars((string) $item['title'], ENT_XML1) . '</title>' . "\n";
            echo '    <link>' . htmlspecialchars($link, ENT_XML1) . '</link>' . "\n";
            echo '    <guid isPermaLink="true">' . htmlspecialchars($link, ENT_XML1) . '</guid>' . "\n";
            echo '    <pubDate>' . htmlspecialchars($pub, ENT_XML1) . '</pubDate>' . "\n";
            if (!empty($item['excerpt'])) {
                echo '    <description>' . htmlspecialchars((string) $item['excerpt'], ENT_XML1) . '</description>' . "\n";
            }
            echo '  </item>' . "\n";
        }
        echo '</channel>' . "\n";
        echo '</rss>' . "\n";
        exit;
    }

    /**
     * Скачивание всех фото новости одним zip-архивом (кнопка «Скачать все фото»).
     * В архив попадают только файлы из каталога публичных загрузок.
     */
    public function photosZip(array $params): void
    {
        $lang = Locale::current();
        $news = News::findPublishedBySlug($params['slug'] ?? '', $lang);
        if (!$news || !class_exists(\ZipArchive::class)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $uploadsDir = rtrim((string) Config::get('paths.public_uploads', ''), '/');
        $uploadsUrl = rtrim((string) Config::get('paths.public_uploads_url', '/uploads/public'), '/');
        $paths = [];
        $cover = trim((string) ($news['image'] ?? ''));
        if ($cover !== '') {
            $paths[] = $cover;
        }
        foreach (NewsImage::forNews((int) $news['id']) as $img) {
            $paths[] = (string) $img['path'];
        }

        $files = [];
        foreach (array_unique($paths) as $url) {
            // Разрешаем только файлы из публичных загрузок; realpath отсекает выход из каталога.
            if ($uploadsDir === '' || strncmp($url, $uploadsUrl . '/', strlen($uploadsUrl) + 1) !== 0) {
                continue;
            }
            $file = realpath($uploadsDir . '/' . substr($url, strlen($uploadsUrl) + 1));
            if ($file !== false && strncmp($file, (string) realpath($uploadsDir), strlen((string) realpath($uploadsDir))) === 0 && is_file($file)) {
                $files[] = $file;
            }
        }
        if ($files === []) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ndz');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($files as $i => $file) {
            $zip->addFile($file, sprintf('photo-%02d-%s', $i + 1, basename($file)));
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . rawurlencode((string) $news['slug']) . '-photos.zip"');
        header('Content-Length: ' . (string) filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    public function show(array $params): void
    {
        $lang = Locale::current();
        $news = News::findPublishedBySlug($params['slug'] ?? '', $lang);

        if (!$news) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        News::incrementViews((int) $news['id']);
        $adjacent = News::adjacent($news, $lang);

        View::render('site/news_show', [
            'news' => $news,
            'gallery' => NewsImage::forNews((int) $news['id']),
            'related' => News::related((int) $news['id'], 4, $lang),
            'prevNews' => $adjacent['prev'],
            'nextNews' => $adjacent['next'],
        ]);
    }
}
