<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

final class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();

        $counts = [
            'news' => (int) Database::pdo()->query('SELECT COUNT(*) FROM news')->fetchColumn(),
            'pages' => (int) Database::pdo()->query('SELECT COUNT(*) FROM pages')->fetchColumn(),
            'projects' => (int) Database::pdo()->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
            'team' => (int) Database::pdo()->query('SELECT COUNT(*) FROM team_members')->fetchColumn(),
            'forms' => (int) Database::pdo()->query('SELECT COUNT(*) FROM forms')->fetchColumn(),
            'submissions_unread' => (int) Database::pdo()->query('SELECT COUNT(*) FROM form_submissions WHERE is_read = 0')->fetchColumn(),
            'files' => (int) Database::pdo()->query('SELECT COUNT(*) FROM files')->fetchColumn(),
        ];

        // Получаем последние 5 действий из журнала аудита
        $recentLogs = \App\Models\AuditLog::search([], 1, 5)['items'];

        // Статистика заявок за последние 7 дней для графика
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $chartData[$date] = 0;
        }
        try {
            $stmt = Database::pdo()->query('SELECT DATE(created_at) as d, COUNT(*) as c FROM form_submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at)');
            foreach ($stmt->fetchAll() as $row) {
                if (isset($chartData[$row['d']])) {
                    $chartData[$row['d']] = (int) $row['c'];
                }
            }
        } catch (\Throwable $e) {
            // Игнорируем ошибки при отсутствии таблицы
        }

        View::render('admin/dashboard', [
            'user' => Auth::user(),
            'counts' => $counts,
            'recentLogs' => $recentLogs,
            'chartData' => $chartData
        ]);
    }
}
