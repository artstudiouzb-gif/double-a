<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\BlockRenderer;
use App\Core\Locale;
use App\Core\View;
use App\Models\Block;
use App\Models\Page;
use App\Models\Widget;

final class PageController
{
    public function home(): void
    {
        $lang = Locale::current();
        $page = Page::findHome($lang);

        if (!$page) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $this->renderPage($page, $lang);
    }

    public function show(array $params): void
    {
        $lang = Locale::current();
        $slug = $params['slug'] ?? '';
        $page = Page::findBySlug($slug, $lang);

        if (!$page) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $this->renderPage($page, $lang);
    }

    private function renderPage(array $page, string $lang): void
    {
        // Скомпилированные блоки (HTML + scoped CSS) кэшируются на диск и
        // сбрасываются при изменении страницы/блоков в админке. Кэш и его TTL
        // управляются в разделе «Производительность».
        $build = static function () use ($page, $lang): array {
            $blocks = Block::forPageLocalized((int) $page['id'], $lang);
            return BlockRenderer::renderPage($blocks);
        };
        if (\App\Models\Setting::get('perf_page_cache', '1') === '1') {
            $ttl = max(0, (int) \App\Models\Setting::get('perf_cache_ttl', '0'));
            $rendered = \App\Core\Cache::remember('page:' . (int) $page['id'] . ':' . $lang, $build, $ttl);
        } else {
            $rendered = $build();
        }

        // Ассеты блоков регистрируются и на попадании в кэш, и при промахе.
        foreach ($rendered['assets'] ?? [] as $assetType) {
            \App\Core\AssetCollector::requireJs($assetType);
        }

        $layoutType = $page['layout_type'] ?? 'no_sidebar';
        $sidebar = null;
        if ($layoutType === 'left_sidebar') {
            $sidebar = ['position' => 'left', 'html' => Widget::renderSidebar('left', $lang)];
        } elseif ($layoutType === 'right_sidebar') {
            $sidebar = ['position' => 'right', 'html' => Widget::renderSidebar('right', $lang)];
        }

        View::render('site/page', [
            'page' => $page,
            'content' => $rendered['html'],
            'blockCss' => $rendered['css'],
            'layoutType' => $layoutType,
            'sidebar' => $sidebar,
        ]);
    }
}
