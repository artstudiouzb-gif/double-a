<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\AdminListQuery;
use App\Core\ConcurrencyException;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Slug;
use App\Core\View;
use App\Models\Block;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\ContentRevision;

final class PageController
{
    public function index(): void
    {
        Auth::requireLogin();
        $filters = AdminListQuery::normalize(
            $_GET,
            ['newest', 'oldest', 'title_asc', 'title_desc'],
            'newest'
        );
        $total = Page::adminCount($filters);
        [$filters, $pages] = AdminListQuery::fitPage($filters, $total);
        View::render('admin/pages/index', [
            'items' => Page::adminList($filters),
            'filters' => $filters,
            'filterParams' => AdminListQuery::urlParams($filters),
            'total' => $total,
            'pages' => $pages,
        ]);
    }

    public function duplicate(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();
        $newId = Page::duplicate((int) $params['id']);
        if ($newId === null) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        \App\Core\Cache::forgetPrefix('page:');
        Flash::success('Страница дублирована как черновик.');
        header('Location: /admin/pages/' . $newId . '/edit');
        exit;
    }

    public function create(): void
    {
        Auth::requireLogin();
        View::render('admin/pages/form', ['page' => null, 'translations' => [], 'error' => null]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        [$data, $error] = $this->collectInput(null);

        if ($error !== null) {
            View::render('admin/pages/form', ['page' => $data, 'translations' => [], 'error' => $error]);
            return;
        }

        $id = Database::transaction(function (\PDO $_pdo) use ($data): int {
            $id = Page::create($data);
            $this->saveTranslations($id);

            return $id;
        });

        Flash::success('Страница создана. Теперь добавьте блоки контента.');
        header('Location: /admin/pages/' . $id . '/edit?draft_saved=page%3Anew');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();

        $page = Page::findById((int) $params['id']);
        if (!$page) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $blockLang = $this->resolveBlockLang();

        View::render('admin/pages/form', [
            'page' => $page,
            'translations' => PageTranslation::forPage((int) $page['id']),
            'error' => null,
            'blocks' => Block::forPage((int) $page['id'], $blockLang),
            'blockLang' => $blockLang,
        ]);
    }

    /**
     * Предпросмотр страницы до публикации (группа 5.2). Рендерит страницу
     * (в т.ч. черновик) со всеми блоками и scoped CSS, но: доступ только
     * авторизованным, noindex, без записи в кэш и sitemap.
     */
    public function preview(array $params): void
    {
        Auth::requireLogin();

        $page = Page::findById((int) $params['id']);
        if (!$page) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $lang = $this->resolveBlockLang();
        $page = Page::localize($page, $lang);

        // Рендерим блоки заново, минуя дисковый кэш (показываем актуальный черновик).
        $blocks = Block::forPageLocalized((int) $page['id'], $lang);
        $rendered = \App\Core\BlockRenderer::renderPage($blocks);
        foreach ($rendered['assets'] ?? [] as $assetType) {
            \App\Core\AssetCollector::requireJs($assetType);
        }

        $layoutType = $page['layout_type'] ?? 'no_sidebar';
        $sidebar = null;
        if ($layoutType === 'left_sidebar') {
            $sidebar = ['position' => 'left', 'html' => \App\Models\Widget::renderSidebar('left', $lang)];
        } elseif ($layoutType === 'right_sidebar') {
            $sidebar = ['position' => 'right', 'html' => \App\Models\Widget::renderSidebar('right', $lang)];
        }

        View::render('site/page', [
            'page' => $page,
            'content' => $rendered['html'],
            'blockCss' => $rendered['css'],
            'layoutType' => $layoutType,
            'sidebar' => $sidebar,
            'robotsNoindex' => true,
            'previewNotice' => true,
        ]);
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $id = (int) $params['id'];
        $page = Page::findById($id);
        if (!$page) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        if (!ContentRevision::isFresh('page', $id, (string) ($_POST['expected_updated_at'] ?? ''))) {
            $blockLang = $this->resolveBlockLang();
            View::render('admin/pages/form', [
                'page' => $page,
                'translations' => PageTranslation::forPage($id),
                'error' => 'Страница уже была изменена в другой вкладке или другим пользователем. Текущие данные перезагружены; восстановите локальный черновик и проверьте изменения.',
                'blocks' => Block::forPage($id, $blockLang),
                'blockLang' => $blockLang,
            ]);
            return;
        }

        [$data, $error] = $this->collectInput($id, $page);

        if ($error !== null) {
            $blockLang = $this->resolveBlockLang();
            View::render('admin/pages/form', [
                'page' => array_merge($page, $data),
                'translations' => PageTranslation::forPage($id),
                'error' => $error,
                'blocks' => Block::forPage($id, $blockLang),
                'blockLang' => $blockLang,
            ]);
            return;
        }

        $expectedVersion = (int) ($_POST['expected_lock_version'] ?? 0);
        try {
            Database::transaction(function (\PDO $_pdo) use ($id, $data, $expectedVersion): void {
                ContentRevision::capture('page', $id, Auth::id());
                Page::update($id, $data, $expectedVersion);
                $this->saveTranslations($id);
            });
        } catch (ConcurrencyException) {
            $page = Page::findById($id) ?? $page;
            $blockLang = $this->resolveBlockLang();
            View::render('admin/pages/form', [
                'page' => $page,
                'translations' => PageTranslation::forPage($id),
                'error' => 'Страница уже была изменена в другой вкладке или другим пользователем. Текущие данные перезагружены; восстановите локальный черновик и проверьте изменения.',
                'blocks' => Block::forPage($id, $blockLang),
                'blockLang' => $blockLang,
            ]);
            return;
        }
        \App\Core\Cache::forgetPrefix('page:' . $id);

        Flash::success('Страница обновлена.');
        header('Location: /admin/pages/' . $id . '/edit?draft_saved=page%3A' . $id);
        exit;
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        Page::delete((int) $params['id']);
        \App\Core\Cache::forgetPrefix('page:' . (int) $params['id']);
        Flash::success('Страница удалена.');
        header('Location: ' . AdminListQuery::returnPath('/admin/pages', $_POST['return_query'] ?? ''));
        exit;
    }

    private function resolveBlockLang(): string
    {
        $lang = (string) ($_GET['block_lang'] ?? Language::defaultCode());

        return Language::isActive($lang) ? $lang : Language::defaultCode();
    }

    private function saveTranslations(int $pageId): void
    {
        $defaultCode = Language::defaultCode();
        $input = (array) ($_POST['translations'] ?? []);

        foreach (Language::active() as $lang) {
            $code = (string) $lang['code'];
            if ($code === $defaultCode) {
                continue;
            }
            $t = (array) ($input[$code] ?? []);
            PageTranslation::upsert($pageId, $code, [
                'title' => trim((string) ($t['title'] ?? '')),
                'meta_title' => trim((string) ($t['meta_title'] ?? '')),
                'meta_description' => trim((string) ($t['meta_description'] ?? '')),
                'lead' => trim((string) ($t['lead'] ?? '')),
            ]);
        }
    }

    /**
     * @return array{0: array, 1: string|null}
     */
    private function collectInput(?int $id, ?array $existing = null): array
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slugInput = trim((string) ($_POST['slug'] ?? ''));
        $metaTitle = trim((string) ($_POST['meta_title'] ?? ''));
        $metaDescription = trim((string) ($_POST['meta_description'] ?? ''));
        $lead = trim((string) ($_POST['lead'] ?? ''));
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $isHome = !empty($_POST['is_home']);
        $layoutType = in_array($_POST['layout_type'] ?? '', ['no_sidebar', 'left_sidebar', 'right_sidebar'], true)
            ? $_POST['layout_type'] : 'no_sidebar';

        if ($title === '') {
            return [['title' => $title, 'slug' => $slugInput, 'status' => $status], 'Укажите заголовок страницы.'];
        }

        $slug = $slugInput !== '' ? Slug::make($slugInput) : Slug::make($title);
        if (Page::slugExists($slug, $id)) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }

        $data = [
            'title' => $title,
            'slug' => $slug,
            'meta_title' => $metaTitle !== '' ? $metaTitle : null,
            'meta_description' => $metaDescription !== '' ? $metaDescription : null,
            'lead' => $lead !== '' ? $lead : null,
            'status' => $status,
            'is_home' => $isHome,
            'layout_type' => $layoutType,
            'hide_chrome' => !empty($_POST['hide_chrome']), // лендинг (группа 6)
            'transparent_header' => !empty($_POST['transparent_header']),
        ];

        return [$data, null];
    }
}
