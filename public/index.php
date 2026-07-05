<?php

declare(strict_types=1);

require __DIR__ . '/../app/Core/bootstrap.php';

use App\Controllers\Admin\AuthController as AdminAuthController;
use App\Controllers\Admin\BlockController as AdminBlockController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\FileController as AdminFileController;
use App\Controllers\Admin\FormController as AdminFormController;
use App\Controllers\Admin\NewsController as AdminNewsController;
use App\Controllers\Admin\PageController as AdminPageController;
use App\Controllers\Admin\ProjectController as AdminProjectController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\TeamController as AdminTeamController;
use App\Controllers\Site\FormController as SiteFormController;
use App\Controllers\Site\NewsController as SiteNewsController;
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

// --- Admin: дашборд ---
$router->get('/admin', [DashboardController::class, 'index']);

// --- Admin: новости ---
$router->get('/admin/news', [AdminNewsController::class, 'index']);
$router->get('/admin/news/create', [AdminNewsController::class, 'create']);
$router->post('/admin/news/create', [AdminNewsController::class, 'store']);
$router->get('/admin/news/{id}/edit', [AdminNewsController::class, 'edit']);
$router->post('/admin/news/{id}/edit', [AdminNewsController::class, 'update']);
$router->post('/admin/news/{id}/delete', [AdminNewsController::class, 'destroy']);

// --- Admin: страницы + конструктор блоков ---
$router->get('/admin/pages', [AdminPageController::class, 'index']);
$router->get('/admin/pages/create', [AdminPageController::class, 'create']);
$router->post('/admin/pages/create', [AdminPageController::class, 'store']);
$router->get('/admin/pages/{id}/edit', [AdminPageController::class, 'edit']);
$router->post('/admin/pages/{id}/edit', [AdminPageController::class, 'update']);
$router->post('/admin/pages/{id}/delete', [AdminPageController::class, 'destroy']);
$router->post('/admin/pages/{id}/blocks/add', [AdminBlockController::class, 'store']);

$router->get('/admin/blocks/{id}/edit', [AdminBlockController::class, 'edit']);
$router->post('/admin/blocks/{id}/edit', [AdminBlockController::class, 'update']);
$router->post('/admin/blocks/{id}/delete', [AdminBlockController::class, 'destroy']);
$router->post('/admin/blocks/{id}/move', [AdminBlockController::class, 'move']);

// --- Admin: проекты ---
$router->get('/admin/projects', [AdminProjectController::class, 'index']);
$router->get('/admin/projects/create', [AdminProjectController::class, 'create']);
$router->post('/admin/projects/create', [AdminProjectController::class, 'store']);
$router->get('/admin/projects/{id}/edit', [AdminProjectController::class, 'edit']);
$router->post('/admin/projects/{id}/edit', [AdminProjectController::class, 'update']);
$router->post('/admin/projects/{id}/delete', [AdminProjectController::class, 'destroy']);

// --- Admin: команда ---
$router->get('/admin/team', [AdminTeamController::class, 'index']);
$router->get('/admin/team/create', [AdminTeamController::class, 'create']);
$router->post('/admin/team/create', [AdminTeamController::class, 'store']);
$router->get('/admin/team/{id}/edit', [AdminTeamController::class, 'edit']);
$router->post('/admin/team/{id}/edit', [AdminTeamController::class, 'update']);
$router->post('/admin/team/{id}/delete', [AdminTeamController::class, 'destroy']);

// --- Admin: формы и заявки ---
$router->get('/admin/forms', [AdminFormController::class, 'index']);
$router->get('/admin/forms/create', [AdminFormController::class, 'create']);
$router->post('/admin/forms/create', [AdminFormController::class, 'store']);
$router->get('/admin/forms/{id}/edit', [AdminFormController::class, 'edit']);
$router->post('/admin/forms/{id}/edit', [AdminFormController::class, 'update']);
$router->post('/admin/forms/{id}/delete', [AdminFormController::class, 'destroy']);
$router->get('/admin/forms/{id}/submissions', [AdminFormController::class, 'submissions']);
$router->post('/admin/forms/submissions/{id}/delete', [AdminFormController::class, 'deleteSubmission']);

// --- Admin: настройки дизайна ---
$router->get('/admin/settings', [SettingsController::class, 'index']);
$router->post('/admin/settings', [SettingsController::class, 'update']);

// --- Admin: файловый менеджер ---
$router->get('/admin/files', [AdminFileController::class, 'index']);
$router->post('/admin/files/upload', [AdminFileController::class, 'upload']);
$router->post('/admin/files/{id}/delete', [AdminFileController::class, 'destroy']);
$router->post('/admin/files/{id}/regenerate-token', [AdminFileController::class, 'regenerateToken']);

// --- Публичный сайт ---
$router->get('/', [SitePageController::class, 'home']);
$router->get('/news', [SiteNewsController::class, 'index']);
$router->get('/news/{slug}', [SiteNewsController::class, 'show']);
$router->post('/forms/{slug}/submit', [SiteFormController::class, 'submit']);
$router->get('/{slug}', [SitePageController::class, 'show']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
