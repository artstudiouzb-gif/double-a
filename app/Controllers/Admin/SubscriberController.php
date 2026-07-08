<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\Subscriber;

/** Подписчики email-дайджеста: список, удаление. Только супер-админ. */
final class SubscriberController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();
        View::render('admin/subscribers/index', [
            'items' => Subscriber::all(),
            'total' => Subscriber::count(),
        ]);
    }

    public function destroy(array $params): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        Subscriber::delete((int) $params['id']);
        Flash::success('Подписчик удалён.');
        header('Location: /admin/subscribers');
        exit;
    }
}
