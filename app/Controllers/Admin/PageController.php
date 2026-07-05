<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Slug;
use App\Core\View;
use App\Models\Block;
use App\Models\Page;

final class PageController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('admin/pages/index', ['items' => Page::all()]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        View::render('admin/pages/form', ['page' => null, 'error' => null]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        [$data, $error] = $this->collectInput(null);

        if ($error !== null) {
            View::render('admin/pages/form', ['page' => $data, 'error' => $error]);
            return;
        }

        $id = Page::create($data);
        Flash::success('Страница создана. Теперь добавьте блоки контента.');
        header('Location: /admin/pages/' . $id . '/edit');
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

        View::render('admin/pages/form', [
            'page' => $page,
            'error' => null,
            'blocks' => Block::forPage((int) $page['id']),
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

        [$data, $error] = $this->collectInput($id, $page);

        if ($error !== null) {
            View::render('admin/pages/form', [
                'page' => array_merge($page, $data),
                'error' => $error,
                'blocks' => Block::forPage($id),
            ]);
            return;
        }

        Page::update($id, $data);
        Flash::success('Страница обновлена.');
        header('Location: /admin/pages/' . $id . '/edit');
        exit;
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        Page::delete((int) $params['id']);
        Flash::success('Страница удалена.');
        header('Location: /admin/pages');
        exit;
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
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $isHome = !empty($_POST['is_home']);

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
            'status' => $status,
            'is_home' => $isHome,
        ];

        return [$data, null];
    }
}
