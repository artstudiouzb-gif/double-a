<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;

final class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('admin/dashboard', ['user' => Auth::user()]);
    }
}
