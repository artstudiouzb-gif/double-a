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

        View::render('admin/dashboard', ['user' => Auth::user(), 'counts' => $counts]);
    }
}
