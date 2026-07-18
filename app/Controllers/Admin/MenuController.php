<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\Language;
use App\Models\MenuItem;
use App\Models\Page;

final class MenuController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/menu/index', [
            'tree' => MenuItem::allTree(),
            'items' => MenuItem::all(),
            'pages' => Page::filter('published'),
            'languages' => Language::active(),
        ]);
    }

    public function store(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        [$data, $error] = $this->collectInput();
        if ($error !== null) {
            Flash::error($error);
            header('Location: /admin/menu');
            exit;
        }

        $parentError = MenuItem::validateParent($data['parent_id'], null, $data['lang']);
        if ($parentError !== null) {
            Flash::error($parentError);
            header('Location: /admin/menu');
            exit;
        }

        MenuItem::create($data);
        Flash::success('Пункт меню добавлен.');
        header('Location: /admin/menu');
        exit;
    }

    public function update(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $item = MenuItem::findById((int) $params['id']);
        if (!$item) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        [$data, $error] = $this->collectInput();
        if ($error !== null) {
            Flash::error($error);
            header('Location: /admin/menu');
            exit;
        }

        $parentError = MenuItem::validateParent($data['parent_id'], (int) $item['id'], $data['lang']);
        if ($parentError !== null) {
            Flash::error($parentError);
            header('Location: /admin/menu');
            exit;
        }

        MenuItem::update((int) $item['id'], $data);
        Flash::success('Пункт меню обновлён.');
        header('Location: /admin/menu');
        exit;
    }

    /**
     * AJAX: пакетное сохранение порядка и вложенности (drag-and-drop, задача 3).
     */
    public function reorder(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();
        header('Content-Type: application/json');

        $ids = $_POST['id'] ?? [];
        $parents = $_POST['parent_id'] ?? [];
        if (!is_array($ids)) {
            echo json_encode(['ok' => false, 'error' => 'bad_request']);
            return;
        }

        $rows = [];
        foreach (array_values($ids) as $i => $id) {
            $parent = $parents[$i] ?? '';
            $rows[] = [
                'id' => (int) $id,
                'parent_id' => ($parent === '' || $parent === '0') ? null : (int) $parent,
                'sort_order' => $i + 1,
            ];
        }

        try {
            MenuItem::reorder($rows);
            echo json_encode(['ok' => true]);
        } catch (\DomainException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('Не удалось сохранить порядок меню', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'server_error']);
        }
    }

    public function destroy(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        MenuItem::delete((int) $params['id']);
        Flash::success('Пункт меню удалён.');
        header('Location: /admin/menu');
        exit;
    }

    public function move(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
        MenuItem::move((int) $params['id'], $direction);
        header('Location: /admin/menu');
        exit;
    }

    /**
     * @return array{0: array, 1: string|null}
     */
    private function collectInput(): array
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        // Меню теперь всегда привязано к конкретному языку (без «Все языки»):
        // пустой/неактивный — на язык по умолчанию.
        $lang = (string) ($_POST['lang'] ?? '');
        if ($lang === '' || !Language::isActive($lang)) {
            $lang = Language::defaultCode();
        }
        $isDivider = !empty($_POST['is_divider']);
        $iconSvg = (string) ($_POST['icon_svg'] ?? '');
        $urlType = in_array($_POST['url_type'] ?? '', ['page', 'news_index', 'custom'], true) ? $_POST['url_type'] : 'custom';
        $urlValue = match ($urlType) {
            'page' => trim((string) ($_POST['page_slug'] ?? $_POST['url_value'] ?? '')),
            'custom' => trim((string) ($_POST['custom_url'] ?? $_POST['url_value'] ?? '')),
            default => '',
        };

        // Разделитель — визуальный элемент без ссылки и, как правило, без названия.
        if ($isDivider) {
            return [[
                'title' => $title !== '' ? $title : '—',
                'lang' => $lang,
                'icon_svg' => null,
                'is_divider' => true,
                'url_type' => 'custom',
                'url_value' => null,
                'parent_id' => null,
                'is_active' => !empty($_POST['is_active']),
            ], null];
        }

        if ($title === '') {
            return [[], 'Укажите название пункта меню.'];
        }
        if ($urlType === 'page' && $urlValue === '') {
            return [[], 'Выберите страницу для пункта меню.'];
        }
        if ($urlType === 'page' && Page::findBySlug($urlValue) === null) {
            return [[], 'Выбранная страница не найдена. Обновите список и попробуйте снова.'];
        }
        if ($urlType === 'custom' && $urlValue === '') {
            return [[], 'Укажите URL для пункта меню.'];
        }
        if ($urlType === 'custom' && !\App\Core\UrlGuard::isSafeLink($urlValue)) {
            return [[], 'Недопустимый URL: разрешены http(s)-ссылки, относительные пути, mailto/tel.'];
        }
        if ($urlType === 'news_index') {
            $urlValue = '';
        }

        $parentRaw = trim((string) ($_POST['parent_id'] ?? ''));
        $parentId = ($parentRaw === '' || $parentRaw === '0') ? null : (int) $parentRaw;

        return [[
            'title' => $title,
            'lang' => $lang,
            'icon_svg' => $iconSvg,
            'is_divider' => false,
            'url_type' => $urlType,
            'url_value' => $urlValue !== '' ? $urlValue : null,
            'parent_id' => $parentId,
            // Раскладка подменю: 0 — обычная выпадашка, 2..4 — мега-меню.
            'mega_columns' => \App\Models\MenuItem::megaColumns($_POST['mega_columns'] ?? 0, $parentId),
            'is_active' => !empty($_POST['is_active']),
        ], null];
    }
}
