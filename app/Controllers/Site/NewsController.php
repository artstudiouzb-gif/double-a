<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Locale;
use App\Core\View;
use App\Models\News;
use App\Models\NewsImage;

final class NewsController
{
    public function index(): void
    {
        $lang = Locale::current();
        $items = News::published(20, 0, $lang);
        View::render('site/news_index', ['items' => $items]);
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
