<?php

declare(strict_types=1);

require __DIR__ . '/../app/Core/bootstrap.php';

use App\Controllers\Admin\AuthController as AdminAuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Site\PageController as SitePageController;
use App\Core\Router;

$router = new Router();

// --- Admin: аутентификация (без требования логина) ---
$router->get('/admin/login', [AdminAuthController::class, 'showLogin']);
$router->post('/admin/login', [AdminAuthController::class, 'login']);
$router->get('/admin/login/2fa', [AdminAuthController::class, 'showTwoFactor']);
$router->post('/admin/login/2fa', [AdminAuthController::class, 'verifyTwoFactor']);
$router->get('/admin/login/2fa-setup', [AdminAuthController::class, 'showTwoFactorSetup']);
$router->post('/admin/login/2fa-setup', [AdminAuthController::class, 'confirmTwoFactorSetup']);
$router->post('/admin/logout', [AdminAuthController::class, 'logout']);

// --- Admin: дашборд (разделы CRUD добавляются на следующем этапе) ---
$router->get('/admin', [DashboardController::class, 'index']);

// --- Публичный сайт: рендер страниц из блоков ---
$router->get('/', [SitePageController::class, 'home']);
$router->get('/{slug}', [SitePageController::class, 'show']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
