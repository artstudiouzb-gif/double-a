<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\Redirect;

/**
 * Менеджер 301/302-редиректов (супер-админ): добавление по одному, массовый
 * импорт списком, включение/выключение, удаление, счётчик срабатываний.
 */
final class RedirectController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/redirects/index', ['items' => Redirect::all()]);
    }

    public function store(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $ok = Redirect::create(
            (string) ($_POST['from_path'] ?? ''),
            (string) ($_POST['to_url'] ?? ''),
            (int) ($_POST['code'] ?? 301)
        );
        if ($ok) {
            Flash::success('Редирект добавлен.');
        } else {
            Flash::error('Не удалось добавить: проверьте адреса (путь «откуда» должен начинаться с «/», не может быть корнем или /admin; такой путь мог быть добавлен ранее).');
        }
        $this->back();
    }

    public function import(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        [$added, $skipped] = Redirect::import((string) ($_POST['list'] ?? ''));
        if ($added > 0) {
            Flash::success("Импортировано редиректов: {$added}." . ($skipped > 0 ? " Пропущено (ошибки/дубли): {$skipped}." : ''));
        } else {
            Flash::error('Ничего не импортировано. Формат строки: «/старый-путь /новый-путь» или «/старый -> https://site.uz/новый», третьим словом можно указать 302.');
        }
        $this->back();
    }

    public function toggle(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        Redirect::setActive((int) $params['id'], ($_POST['active'] ?? '') === '1');
        Flash::success('Состояние редиректа обновлено.');
        $this->back();
    }

    public function destroy(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        Redirect::delete((int) $params['id']);
        Flash::success('Редирект удалён.');
        $this->back();
    }

    private function back(): never
    {
        header('Location: /admin/redirects');
        exit;
    }
}
