<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\ErrorLog;

/**
 * Журнал действий администраторов: просмотр с фильтрами по пользователю,
 * пути, датам; пагинация. Плюс журнал ошибок сайта (вкладка «Ошибки»):
 * понятные объяснения, хранение 7 дней или ручная очистка. Только супер-админ.
 */
final class AuditController
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        Auth::requireSuperAdmin();

        $filters = [
            'user_id' => (int) ($_GET['user_id'] ?? 0),
            'q' => trim((string) ($_GET['q'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = AuditLog::search($filters, $page, self::PER_PAGE);

        View::render('admin/audit/index', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / self::PER_PAGE),
            'filters' => $filters,
            'actors' => AuditLog::actors(),
        ]);
    }

    /** Вкладка «Ошибки»: журнал ошибок сайта с фильтрами и пагинацией. */
    public function errors(): void
    {
        Auth::requireSuperAdmin();

        // Просмотр — удобный момент для авточистки просроченных записей.
        ErrorLog::purgeExpired();

        $filters = [
            'level' => trim((string) ($_GET['level'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = ErrorLog::search($filters, $page, self::PER_PAGE);

        View::render('admin/audit/errors', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / self::PER_PAGE),
            'filters' => $filters,
        ]);
    }

    /** Ручная очистка журнала ошибок. */
    public function errorsClear(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $deleted = ErrorLog::clear();
        Flash::success('Журнал ошибок очищен (удалено записей: ' . $deleted . ').');
        header('Location: /admin/audit/errors');
        exit;
    }
}
