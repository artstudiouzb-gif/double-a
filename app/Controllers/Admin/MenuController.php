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
            'items' => MenuItem::all(),
            'pages' => Page::all(),
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

        MenuItem::update((int) $item['id'], $data);
        Flash::success('Пункт меню обновлён.');
        header('Location: /admin/menu');
        exit;
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
        $lang = (string) ($_POST['lang'] ?? '');
        if ($lang !== '' && !Language::isActive($lang)) {
            $lang = '';
        }
        $urlType = in_array($_POST['url_type'] ?? '', ['page', 'news_index', 'custom'], true) ? $_POST['url_type'] : 'custom';
        $urlValue = trim((string) ($_POST['url_value'] ?? ''));

        if ($title === '') {
            return [[], 'Укажите название пункта меню.'];
        }
        if ($urlType === 'page' && $urlValue === '') {
            return [[], 'Выберите страницу для пункта меню.'];
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

        return [[
            'title' => $title,
            'lang' => $lang,
            'url_type' => $urlType,
            'url_value' => $urlValue !== '' ? $urlValue : null,
            'is_active' => !empty($_POST['is_active']),
        ], null];
    }
}
