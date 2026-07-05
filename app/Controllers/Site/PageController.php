<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\BlockRenderer;
use App\Core\View;
use App\Models\Block;
use App\Models\Page;

final class PageController
{
    public function home(): void
    {
        $page = Page::findHome();

        if (!$page) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $this->renderPage($page);
    }

    public function show(array $params): void
    {
        $slug = $params['slug'] ?? '';
        $page = Page::findBySlug($slug);

        if (!$page) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $this->renderPage($page);
    }

    private function renderPage(array $page): void
    {
        $blocks = Block::forPage((int) $page['id']);
        $rendered = BlockRenderer::renderPage($blocks);

        View::render('site/page', [
            'page' => $page,
            'content' => $rendered['html'],
            'blockCss' => $rendered['css'],
        ]);
    }
}
