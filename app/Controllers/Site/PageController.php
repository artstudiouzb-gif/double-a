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

        // Главная доступна по «/», а не «/{slug}» — со slug'ом это дубль
        // контента. Постоянный редирект на канонический корневой URL.
        if (!empty($page['is_home'])) {
            header('Location: ' . Locale::url('/'), true, 301);
            exit;
        }

        $this->renderPage($page, $lang);
    }

    private function renderPage(array $page, string $lang): void
    {
        // Переключатель языков и hreflang показывают только языки, на которых
        // страница реально наполнена (перевод или собственный стек блоков).
        Locale::setContentLangs(Page::availableLangs((int) $page['id']));

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

        // Кэш страниц общий для всех посетителей, а CSRF-токен и метка времени
        // honeypot — сессионные: подставляем живые значения при каждой отдаче,
        // иначе формы у второго посетителя падают с 419.
        if (!empty($rendered['html']) && str_contains((string) $rendered['html'], 'name="csrf_token"')) {
            $rendered['html'] = preg_replace(
                '/(name="csrf_token" value=")[^"]*(")/',
                '${1}' . htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES) . '${2}',
                (string) $rendered['html']
            );
            $rendered['html'] = preg_replace(
                '/(name="hp_ts" value=")[^"]*(")/',
                '${1}' . time() . '${2}',
                (string) $rendered['html']
            );
        }

        // CSP: инлайн-скрипты в HTML блоков (доверенный контент супер-админа;
        // для editor их режет санитайзер) получают nonce текущего запроса —
        // кэш общий, а nonce одноразовый.
        $rendered['html'] = \App\Core\SecurityHeaders::injectScriptNonce((string) $rendered['html']);

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
        if ($sidebar !== null) {
            // Виджет «произвольный HTML» супер-админа тоже может нести <script>.
            $sidebar['html'] = \App\Core\SecurityHeaders::injectScriptNonce((string) $sidebar['html']);
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
