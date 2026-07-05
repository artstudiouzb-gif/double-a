<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\Language;
use App\Models\Widget;

final class WidgetController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/widgets/index', [
            'left' => Widget::forSidebar('left'),
            'right' => Widget::forSidebar('right'),
            'languages' => Language::active(),
        ]);
    }

    public function create(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/widgets/form', [
            'widget' => null,
            'data' => [],
            'languages' => Language::active(),
            'error' => null,
        ]);
    }

    public function store(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        [$data, $error] = $this->collectInput();
        if ($error !== null) {
            View::render('admin/widgets/form', [
                'widget' => $_POST,
                'data' => $data['data'] ?? [],
                'languages' => Language::active(),
                'error' => $error,
            ]);
            return;
        }

        Widget::create($data);
        Flash::success('Виджет добавлен.');
        header('Location: /admin/widgets');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::requireSuperAdmin();

        $widget = Widget::findById((int) $params['id']);
        if (!$widget) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('admin/widgets/form', [
            'widget' => $widget,
            'data' => json_decode((string) $widget['data'], true) ?: [],
            'languages' => Language::active(),
            'error' => null,
        ]);
    }

    public function update(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $widget = Widget::findById((int) $params['id']);
        if (!$widget) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        [$data, $error] = $this->collectInput((string) $widget['type']);
        if ($error !== null) {
            View::render('admin/widgets/form', [
                'widget' => array_merge($widget, $_POST),
                'data' => $data['data'] ?? [],
                'languages' => Language::active(),
                'error' => $error,
            ]);
            return;
        }

        Widget::update((int) $widget['id'], $data);
        Flash::success('Виджет обновлён.');
        header('Location: /admin/widgets');
        exit;
    }

    public function destroy(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        Widget::delete((int) $params['id']);
        Flash::success('Виджет удалён.');
        header('Location: /admin/widgets');
        exit;
    }

    public function move(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
        Widget::move((int) $params['id'], $direction);
        header('Location: /admin/widgets');
        exit;
    }

    /**
     * @return array{0: array, 1: string|null}
     */
    private function collectInput(?string $fixedType = null): array
    {
        $type = $fixedType ?? (string) ($_POST['type'] ?? '');
        if (!in_array($type, Widget::TYPES, true)) {
            return [[], 'Неизвестный тип виджета.'];
        }

        $sidebar = ($_POST['sidebar'] ?? 'left') === 'right' ? 'right' : 'left';
        $lang = (string) ($_POST['lang'] ?? '');
        if ($lang !== '' && !Language::isActive($lang)) {
            $lang = '';
        }
        $title = trim((string) ($_POST['title'] ?? ''));

        $data = $this->collectData($type);

        return [[
            'sidebar' => $sidebar,
            'type' => $type,
            'title' => $title !== '' ? $title : null,
            'lang' => $lang,
            'data' => $data,
            'is_active' => !empty($_POST['is_active']),
        ], null];
    }

    private function collectData(string $type): array
    {
        return match ($type) {
            'latest_news', 'projects_list', 'team_list' => [
                'count' => max(1, min(20, (int) ($_POST['count'] ?? 5))),
            ],
            'contacts' => [
                'show_socials' => !empty($_POST['show_socials']),
            ],
            'custom_html' => [
                'html' => (string) ($_POST['html'] ?? ''),
            ],
            default => [],
        };
    }
}
