<?php

declare(strict_types=1);

require __DIR__ . '/../app/Core/bootstrap.php';

use App\Controllers\Admin\AuthController as AdminAuthController;
use App\Controllers\Admin\BlockController as AdminBlockController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\FileController as AdminFileController;
use App\Controllers\Admin\FormController as AdminFormController;
use App\Controllers\Admin\HeaderController as AdminHeaderController;
use App\Controllers\Admin\LanguageController as AdminLanguageController;
use App\Controllers\Admin\MenuController as AdminMenuController;
use App\Controllers\Admin\NewsController as AdminNewsController;
use App\Controllers\Admin\PageController as AdminPageController;
use App\Controllers\Admin\ProjectController as AdminProjectController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\TeamController as AdminTeamController;
use App\Controllers\Admin\WidgetController as AdminWidgetController;
use App\Controllers\InstallController;
use App\Controllers\Site\FormController as SiteFormController;
use App\Controllers\Site\NewsController as SiteNewsController;
use App\Controllers\Site\PageController as SitePageController;
use App\Core\Router;

// --- Веб-инсталлятор: пока система не установлена, весь трафик идёт в установщик ---
if (!APP_INSTALLED) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    if (!str_starts_with($requestPath, '/install')) {
        header('Location: /install');
        exit;
    }

    $installRouter = new Router();
    $installRouter->get('/install', [InstallController::class, 'step1']);
    $installRouter->get('/install/step2', [InstallController::class, 'step2']);
    $installRouter->post('/install/step2', [InstallController::class, 'step2Submit']);
    $installRouter->get('/install/step3', [InstallController::class, 'step3']);
    $installRouter->post('/install/step3', [InstallController::class, 'step3Submit']);
    $installRouter->get('/install/step4', [InstallController::class, 'step4']);
    $installRouter->post('/install/step4', [InstallController::class, 'step4Submit']);
    $installRouter->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    exit;
}

// --- Режим обслуживания: гостям 503-заглушка, авторизованным админам —
// полный доступ (чтобы можно было наполнять сайт при закрытом фронтенде). ---
$maintenancePath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if (\App\Models\Setting::get('maintenance_mode', '0') === '1'
    && !\App\Core\Auth::check()
    && !str_starts_with($maintenancePath, '/admin')
    && !str_starts_with($maintenancePath, '/assets')) {
    http_response_code(503);
    header('Retry-After: 3600');
    \App\Core\View::render('errors/maintenance');
    exit;
}

$router = new Router();

// --- Установщик после установки: аппаратно заблокирован (403) ---
$router->get('/install', [InstallController::class, 'step1']);
$router->post('/install/step2', [InstallController::class, 'step2Submit']);
$router->post('/install/step3', [InstallController::class, 'step3Submit']);
$router->post('/install/step4', [InstallController::class, 'step4Submit']);

// --- Admin: аутентификация (без требования логина) ---
$router->get('/admin/login', [AdminAuthController::class, 'showLogin']);
$router->post('/admin/login', [AdminAuthController::class, 'login']);
$router->get('/admin/login/2fa', [AdminAuthController::class, 'showTwoFactor']);
$router->post('/admin/login/2fa', [AdminAuthController::class, 'verifyTwoFactor']);
$router->get('/admin/login/2fa-setup', [AdminAuthController::class, 'showTwoFactorSetup']);
$router->post('/admin/login/2fa-setup', [AdminAuthController::class, 'confirmTwoFactorSetup']);
$router->post('/admin/logout', [AdminAuthController::class, 'logout']);

// --- Admin: восстановление пароля (без требования логина) ---
$router->get('/admin/forgot', [\App\Controllers\Admin\PasswordResetController::class, 'showForgot']);
$router->post('/admin/forgot', [\App\Controllers\Admin\PasswordResetController::class, 'requestReset']);
$router->get('/admin/reset/{token}', [\App\Controllers\Admin\PasswordResetController::class, 'showReset']);
$router->post('/admin/reset', [\App\Controllers\Admin\PasswordResetController::class, 'submitReset']);

// --- Admin: профиль, сессии, backup-коды ---
$router->get('/admin/profile', [\App\Controllers\Admin\ProfileController::class, 'index']);
$router->post('/admin/profile/password', [\App\Controllers\Admin\ProfileController::class, 'changePassword']);
$router->post('/admin/profile/backup-codes', [\App\Controllers\Admin\ProfileController::class, 'regenerateBackupCodes']);
$router->post('/admin/profile/sessions/revoke-others', [\App\Controllers\Admin\ProfileController::class, 'revokeOthers']);
$router->post('/admin/profile/sessions/{id}/revoke', [\App\Controllers\Admin\ProfileController::class, 'revokeSession']);

// --- Admin: дашборд ---
$router->get('/admin', [DashboardController::class, 'index']);

// --- Admin: массовые операции + быстрый поиск (этап 12.4) ---
$router->post('/admin/bulk/{type}', [\App\Controllers\Admin\BulkController::class, 'handle']);
$router->get('/admin/search', [\App\Controllers\Admin\SearchController::class, 'query']);

// --- Admin: новости ---
$router->get('/admin/news', [AdminNewsController::class, 'index']);
$router->get('/admin/news/create', [AdminNewsController::class, 'create']);
$router->post('/admin/news/create', [AdminNewsController::class, 'store']);
$router->get('/admin/news/{id}/edit', [AdminNewsController::class, 'edit']);
$router->post('/admin/news/{id}/edit', [AdminNewsController::class, 'update']);
$router->post('/admin/news/{id}/delete', [AdminNewsController::class, 'destroy']);
$router->post('/admin/news/{id}/duplicate', [AdminNewsController::class, 'duplicate']);
$router->post('/admin/news/{id}/social', [AdminNewsController::class, 'pushSocial']);

// --- Admin: страницы + конструктор блоков ---
$router->get('/admin/pages', [AdminPageController::class, 'index']);
$router->get('/admin/pages/create', [AdminPageController::class, 'create']);
$router->post('/admin/pages/create', [AdminPageController::class, 'store']);
$router->get('/admin/pages/{id}/edit', [AdminPageController::class, 'edit']);
$router->post('/admin/pages/{id}/edit', [AdminPageController::class, 'update']);
$router->post('/admin/pages/{id}/delete', [AdminPageController::class, 'destroy']);
$router->post('/admin/pages/{id}/duplicate', [AdminPageController::class, 'duplicate']);
$router->post('/admin/pages/{id}/blocks/add', [AdminBlockController::class, 'store']);

$router->get('/admin/blocks/{id}/edit', [AdminBlockController::class, 'edit']);
$router->post('/admin/blocks/{id}/edit', [AdminBlockController::class, 'update']);
$router->post('/admin/blocks/{id}/delete', [AdminBlockController::class, 'destroy']);
$router->post('/admin/blocks/{id}/move', [AdminBlockController::class, 'move']);
$router->post('/admin/blocks/reorder', [AdminBlockController::class, 'reorder']);

// --- Admin: шаблоны блоков (сниппеты, задача 133) ---
$router->post('/admin/pages/{id}/snippets/save', [\App\Controllers\Admin\SnippetController::class, 'save']);
$router->post('/admin/pages/{id}/snippets/insert', [\App\Controllers\Admin\SnippetController::class, 'insert']);
$router->post('/admin/snippets/{id}/delete', [\App\Controllers\Admin\SnippetController::class, 'destroy']);

// --- Admin: проекты ---
$router->get('/admin/projects', [AdminProjectController::class, 'index']);
$router->get('/admin/projects/create', [AdminProjectController::class, 'create']);
$router->post('/admin/projects/create', [AdminProjectController::class, 'store']);
$router->get('/admin/projects/{id}/edit', [AdminProjectController::class, 'edit']);
$router->post('/admin/projects/{id}/edit', [AdminProjectController::class, 'update']);
$router->post('/admin/projects/{id}/delete', [AdminProjectController::class, 'destroy']);
$router->post('/admin/projects/{id}/duplicate', [AdminProjectController::class, 'duplicate']);

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

// --- Admin: языки ---
$router->get('/admin/languages', [AdminLanguageController::class, 'index']);
$router->post('/admin/languages/create', [AdminLanguageController::class, 'store']);
$router->post('/admin/languages/{id}/edit', [AdminLanguageController::class, 'update']);
$router->post('/admin/languages/{id}/delete', [AdminLanguageController::class, 'destroy']);

// --- Admin: конструктор меню ---
$router->get('/admin/menu', [AdminMenuController::class, 'index']);
$router->post('/admin/menu/create', [AdminMenuController::class, 'store']);
$router->post('/admin/menu/{id}/edit', [AdminMenuController::class, 'update']);
$router->post('/admin/menu/{id}/delete', [AdminMenuController::class, 'destroy']);
$router->post('/admin/menu/{id}/move', [AdminMenuController::class, 'move']);

// --- Admin: конструктор шапки (mini app) ---
$router->get('/admin/header', [AdminHeaderController::class, 'index']);
$router->post('/admin/header', [AdminHeaderController::class, 'update']);

// --- Admin: боковые виджеты ---
$router->get('/admin/widgets', [AdminWidgetController::class, 'index']);
$router->get('/admin/widgets/create', [AdminWidgetController::class, 'create']);
$router->post('/admin/widgets/create', [AdminWidgetController::class, 'store']);
$router->get('/admin/widgets/{id}/edit', [AdminWidgetController::class, 'edit']);
$router->post('/admin/widgets/{id}/edit', [AdminWidgetController::class, 'update']);
$router->post('/admin/widgets/{id}/delete', [AdminWidgetController::class, 'destroy']);
$router->post('/admin/widgets/{id}/move', [AdminWidgetController::class, 'move']);

// --- Admin: корзина (soft deletes) ---
$router->get('/admin/trash', [\App\Controllers\Admin\TrashController::class, 'index']);
$router->post('/admin/trash/{type}/{id}/restore', [\App\Controllers\Admin\TrashController::class, 'restore']);
$router->post('/admin/trash/{type}/{id}/force-delete', [\App\Controllers\Admin\TrashController::class, 'forceDelete']);

// --- Admin: пользователи (только супер-администратор) ---
$router->get('/admin/users', [\App\Controllers\Admin\UserController::class, 'index']);
$router->post('/admin/users/create', [\App\Controllers\Admin\UserController::class, 'store']);
$router->post('/admin/users/{id}/delete', [\App\Controllers\Admin\UserController::class, 'destroy']);

// --- Admin: настройки дизайна ---
$router->get('/admin/settings', [SettingsController::class, 'index']);
$router->post('/admin/settings', [SettingsController::class, 'update']);

// --- Admin: авто-публикация в соцсети (только супер-админ) ---
$router->get('/admin/social', [\App\Controllers\Admin\SocialController::class, 'index']);
$router->post('/admin/social', [\App\Controllers\Admin\SocialController::class, 'update']);

// --- Admin: резервное копирование ---
$router->post('/admin/backup', [\App\Controllers\Admin\BackupController::class, 'create']);

// --- Admin: файловый менеджер ---
$router->get('/admin/files', [AdminFileController::class, 'index']);
$router->get('/admin/media/list', [AdminFileController::class, 'library']);
$router->post('/admin/files/upload', [AdminFileController::class, 'upload']);
$router->post('/admin/files/chunk', [\App\Controllers\Admin\ChunkedUploadController::class, 'chunk']);
$router->post('/admin/files/{id}/delete', [AdminFileController::class, 'destroy']);
$router->post('/admin/files/{id}/regenerate-token', [AdminFileController::class, 'regenerateToken']);

// --- Health-check (мониторинг) ---
$router->get('/health', [\App\Controllers\Site\HealthController::class, 'index']);

// --- SEO ---
$router->get('/sitemap.xml', [\App\Controllers\Site\SitemapController::class, 'xml']);
$router->get('/robots.txt', [\App\Controllers\Site\SitemapController::class, 'robots']);

// --- Публичный сайт ---
$router->get('/', [SitePageController::class, 'home']);
$router->get('/news', [SiteNewsController::class, 'index']);
$router->get('/news/{slug}', [SiteNewsController::class, 'show']);
$router->post('/forms/{slug}/submit', [SiteFormController::class, 'submit']);
$router->get('/{slug}', [SitePageController::class, 'show']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
