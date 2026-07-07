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
        $items = News::published(20, 0, $lang);
        View::render('site/news_index', ['items' => $items]);
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

    public function show(array $params): void
    {
        $lang = Locale::current();
        $news = News::findPublishedBySlug($params['slug'] ?? '', $lang);

        if (!$news) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('site/news_show', [
            'news' => $news,
            'gallery' => NewsImage::forNews((int) $news['id']),
        ]);
    }
}
