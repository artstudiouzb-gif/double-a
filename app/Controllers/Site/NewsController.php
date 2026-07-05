<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\View;
use App\Models\News;

final class NewsController
{
    public function index(): void
    {
        $items = News::published(20, 0);
        View::render('site/news_index', ['items' => $items]);
    }

    public function show(array $params): void
    {
        $news = News::findPublishedBySlug($params['slug'] ?? '');

        if (!$news) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('site/news_show', ['news' => $news]);
    }
}
