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
use App\Controllers\Admin\ContentRevisionController as AdminContentRevisionController;
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
    && !(\App\Core\Session::hasCookie() && \App\Core\Auth::check())
    && !str_starts_with($maintenancePath, '/admin')
    && !str_starts_with($maintenancePath, '/repo')
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
$router->post('/admin/login/2fa/resend', [AdminAuthController::class, 'resendCode']);
$router->post('/admin/logout', [AdminAuthController::class, 'logout']);

// --- Admin: восстановление пароля (без требования логина) ---
$router->get('/admin/forgot', [\App\Controllers\Admin\PasswordResetController::class, 'showForgot']);
$router->post('/admin/forgot', [\App\Controllers\Admin\PasswordResetController::class, 'requestReset']);
$router->get('/admin/reset/{token}', [\App\Controllers\Admin\PasswordResetController::class, 'showReset']);
$router->post('/admin/reset', [\App\Controllers\Admin\PasswordResetController::class, 'submitReset']);

// --- Admin: профиль, сессии, backup-коды ---
$router->get('/admin/profile', [\App\Controllers\Admin\ProfileController::class, 'index']);
$router->post('/admin/profile/password', [\App\Controllers\Admin\ProfileController::class, 'changePassword']);
$router->post('/admin/profile/phone', [\App\Controllers\Admin\ProfileController::class, 'updatePhone']);
$router->post('/admin/profile/telegram/link', [\App\Controllers\Admin\ProfileController::class, 'linkTelegram']);
$router->post('/admin/profile/telegram/unlink', [\App\Controllers\Admin\ProfileController::class, 'unlinkTelegram']);
$router->post('/admin/profile/sessions/revoke-others', [\App\Controllers\Admin\ProfileController::class, 'revokeOthers']);
$router->post('/admin/profile/sessions/{id}/revoke', [\App\Controllers\Admin\ProfileController::class, 'revokeSession']);

// --- Admin: дашборд ---
$router->get('/admin', [DashboardController::class, 'index']);

// --- Admin: массовые операции + быстрый поиск (этап 12.4) ---
$router->post('/admin/bulk/{type}', [\App\Controllers\Admin\BulkController::class, 'handle']);
$router->get('/admin/search', [\App\Controllers\Admin\SearchController::class, 'query']);

// --- Admin: история версий страниц, новостей и проектов ---
$router->get('/admin/revisions/{type}/{id}', [AdminContentRevisionController::class, 'index']);
$router->post('/admin/revisions/{type}/{id}/{revisionId}/restore', [AdminContentRevisionController::class, 'restore']);

// --- Admin: новости ---
$router->get('/admin/news', [AdminNewsController::class, 'index']);
$router->get('/admin/news/create', [AdminNewsController::class, 'create']);
$router->post('/admin/news/create', [AdminNewsController::class, 'store']);
$router->get('/admin/news/{id}/edit', [AdminNewsController::class, 'edit']);
$router->get('/admin/news/{id}/preview', [AdminNewsController::class, 'preview']);
$router->post('/admin/news/{id}/edit', [AdminNewsController::class, 'update']);
$router->post('/admin/news/{id}/delete', [AdminNewsController::class, 'destroy']);
$router->post('/admin/news/{id}/duplicate', [AdminNewsController::class, 'duplicate']);
$router->post('/admin/news/{id}/social', [AdminNewsController::class, 'pushSocial']);

// --- Admin: страницы + конструктор блоков ---
$router->get('/admin/pages', [AdminPageController::class, 'index']);
$router->get('/admin/pages/create', [AdminPageController::class, 'create']);
$router->post('/admin/pages/create', [AdminPageController::class, 'store']);
$router->get('/admin/pages/{id}/edit', [AdminPageController::class, 'edit']);
$router->get('/admin/pages/{id}/preview', [AdminPageController::class, 'preview']);
$router->post('/admin/pages/{id}/edit', [AdminPageController::class, 'update']);
$router->post('/admin/pages/{id}/delete', [AdminPageController::class, 'destroy']);
$router->post('/admin/pages/{id}/duplicate', [AdminPageController::class, 'duplicate']);
$router->post('/admin/pages/{id}/blocks/add', [AdminBlockController::class, 'store']);

$router->get('/admin/blocks/{id}/edit', [AdminBlockController::class, 'edit']);
$router->post('/admin/blocks/{id}/edit', [AdminBlockController::class, 'update']);
$router->post('/admin/blocks/{id}/delete', [AdminBlockController::class, 'destroy']);
$router->post('/admin/blocks/{id}/move', [AdminBlockController::class, 'move']);
$router->post('/admin/blocks/{id}/toggle', [AdminBlockController::class, 'toggle']);
$router->post('/admin/blocks/reorder', [AdminBlockController::class, 'reorder']);
$router->get('/admin/blocks/{id}/revisions', [AdminBlockController::class, 'revisions']);
$router->post('/admin/blocks/{id}/revisions/restore', [AdminBlockController::class, 'restoreRevision']);

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
$router->post('/admin/menu/reorder', [AdminMenuController::class, 'reorder']);
$router->post('/admin/menu/{id}/edit', [AdminMenuController::class, 'update']);
$router->post('/admin/menu/{id}/delete', [AdminMenuController::class, 'destroy']);
$router->post('/admin/menu/{id}/move', [AdminMenuController::class, 'move']);

// --- Admin: конструктор шапки (mini app) ---
$router->get('/admin/header', [AdminHeaderController::class, 'index']);
$router->post('/admin/header', [AdminHeaderController::class, 'update']);

// --- Admin: конструктор подвала ---
$router->get('/admin/footer', [\App\Controllers\Admin\FooterController::class, 'index']);
$router->post('/admin/footer', [\App\Controllers\Admin\FooterController::class, 'update']);

// --- Admin: производительность ---
$router->get('/admin/performance', [\App\Controllers\Admin\PerformanceController::class, 'index']);
$router->post('/admin/performance', [\App\Controllers\Admin\PerformanceController::class, 'update']);
$router->post('/admin/performance/clear-cache', [\App\Controllers\Admin\PerformanceController::class, 'clearCache']);
$router->post('/admin/cloudflare/verify', [\App\Controllers\Admin\PerformanceController::class, 'cloudflareVerify']);
$router->post('/admin/cloudflare/purge', [\App\Controllers\Admin\PerformanceController::class, 'cloudflarePurge']);

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
$router->get('/admin/audit', [\App\Controllers\Admin\AuditController::class, 'index']);
$router->get('/admin/audit/errors', [\App\Controllers\Admin\AuditController::class, 'errors']);
$router->post('/admin/audit/errors/clear', [\App\Controllers\Admin\AuditController::class, 'errorsClear']);
$router->get('/admin/subscribers', [\App\Controllers\Admin\SubscriberController::class, 'index']);
$router->post('/admin/subscribers/{id}/delete', [\App\Controllers\Admin\SubscriberController::class, 'destroy']);
$router->get('/admin/albums', [\App\Controllers\Admin\AlbumController::class, 'index']);
$router->post('/admin/albums/create', [\App\Controllers\Admin\AlbumController::class, 'store']);
$router->get('/admin/albums/{id}/edit', [\App\Controllers\Admin\AlbumController::class, 'edit']);
$router->post('/admin/albums/{id}/update', [\App\Controllers\Admin\AlbumController::class, 'update']);
$router->post('/admin/albums/{id}/delete', [\App\Controllers\Admin\AlbumController::class, 'destroy']);
$router->post('/admin/albums/{id}/images/add', [\App\Controllers\Admin\AlbumController::class, 'addImage']);
$router->post('/admin/albums/{id}/images/{imageId}/delete', [\App\Controllers\Admin\AlbumController::class, 'deleteImage']);
$router->get('/admin/videos', [\App\Controllers\Admin\VideoController::class, 'index']);
$router->post('/admin/videos/create', [\App\Controllers\Admin\VideoController::class, 'store']);
$router->get('/admin/videos/{id}/edit', [\App\Controllers\Admin\VideoController::class, 'edit']);
$router->post('/admin/videos/{id}/update', [\App\Controllers\Admin\VideoController::class, 'update']);
$router->post('/admin/videos/{id}/delete', [\App\Controllers\Admin\VideoController::class, 'destroy']);
$router->get('/admin/redirects', [\App\Controllers\Admin\RedirectController::class, 'index']);
$router->post('/admin/redirects/create', [\App\Controllers\Admin\RedirectController::class, 'store']);
$router->post('/admin/redirects/import', [\App\Controllers\Admin\RedirectController::class, 'import']);
$router->post('/admin/redirects/{id}/toggle', [\App\Controllers\Admin\RedirectController::class, 'toggle']);
$router->post('/admin/redirects/{id}/delete', [\App\Controllers\Admin\RedirectController::class, 'destroy']);
$router->post('/admin/redirects/404/{id}/delete', [\App\Controllers\Admin\RedirectController::class, 'dismissNotFound']);
$router->get('/admin/users', [\App\Controllers\Admin\UserController::class, 'index']);
$router->post('/admin/users/create', [\App\Controllers\Admin\UserController::class, 'store']);
$router->post('/admin/users/{id}/delete', [\App\Controllers\Admin\UserController::class, 'destroy']);

// --- Admin: конструктор типов контента (супер-админ) ---
$router->get('/admin/content-types', [\App\Controllers\Admin\ContentTypeController::class, 'index']);
$router->post('/admin/content-types/create', [\App\Controllers\Admin\ContentTypeController::class, 'store']);
$router->get('/admin/content-types/{id}/fields', [\App\Controllers\Admin\ContentTypeController::class, 'fields']);
$router->post('/admin/content-types/{id}/fields', [\App\Controllers\Admin\ContentTypeController::class, 'saveFields']);
$router->post('/admin/content-types/{id}/delete', [\App\Controllers\Admin\ContentTypeController::class, 'destroy']);

// --- Admin: авто-CRUD записей типов контента ---
$router->get('/admin/content/{type}', [\App\Controllers\Admin\ContentEntryController::class, 'index']);
$router->get('/admin/content/{type}/create', [\App\Controllers\Admin\ContentEntryController::class, 'create']);
$router->post('/admin/content/{type}/create', [\App\Controllers\Admin\ContentEntryController::class, 'store']);
$router->get('/admin/content/{type}/{id}/edit', [\App\Controllers\Admin\ContentEntryController::class, 'edit']);
$router->post('/admin/content/{type}/{id}/edit', [\App\Controllers\Admin\ContentEntryController::class, 'update']);
$router->post('/admin/content/{type}/{id}/delete', [\App\Controllers\Admin\ContentEntryController::class, 'destroy']);

// --- Admin: тема-билдер (дизайн сайта, супер-админ) ---
$router->get('/admin/design', [\App\Controllers\Admin\DesignController::class, 'index']);
$router->post('/admin/design', [\App\Controllers\Admin\DesignController::class, 'update']);
$router->post('/admin/design/preset', [\App\Controllers\Admin\DesignController::class, 'applyPreset']);
$router->post('/admin/design/preset/save', [\App\Controllers\Admin\DesignController::class, 'savePreset']);
$router->post('/admin/design/preset/delete', [\App\Controllers\Admin\DesignController::class, 'deletePreset']);
$router->get('/admin/design/preview', [\App\Controllers\Admin\DesignController::class, 'preview']);

// --- Admin: настройки дизайна ---
$router->get('/admin/settings', [SettingsController::class, 'index']);
$router->post('/admin/settings', [SettingsController::class, 'update']);
$router->post('/admin/settings/demo-content', [SettingsController::class, 'seedDemo']);

// --- Admin: авто-публикация в соцсети (только супер-админ) ---
$router->get('/admin/social', [\App\Controllers\Admin\SocialController::class, 'index']);
$router->post('/admin/social', [\App\Controllers\Admin\SocialController::class, 'update']);

// --- Admin: исходящие вебхуки (только супер-админ) ---
$router->get('/admin/webhooks', [\App\Controllers\Admin\WebhookController::class, 'index']);
$router->post('/admin/webhooks/create', [\App\Controllers\Admin\WebhookController::class, 'store']);
$router->post('/admin/webhooks/{id}/edit', [\App\Controllers\Admin\WebhookController::class, 'update']);
$router->post('/admin/webhooks/{id}/delete', [\App\Controllers\Admin\WebhookController::class, 'destroy']);

// --- Admin: резервное копирование ---
$router->post('/admin/backup', [\App\Controllers\Admin\BackupController::class, 'create']);

// --- Admin: файловый менеджер ---
$router->get('/admin/files', [AdminFileController::class, 'index']);
$router->get('/admin/media/list', [AdminFileController::class, 'library']);
$router->post('/admin/files/upload', [AdminFileController::class, 'upload']);
$router->post('/admin/files/chunk', [\App\Controllers\Admin\ChunkedUploadController::class, 'chunk']);
$router->post('/admin/files/{id}/delete', [AdminFileController::class, 'destroy']);
$router->post('/admin/files/{id}/regenerate-token', [AdminFileController::class, 'regenerateToken']);

// --- Admin: защищённое файловое хранилище (супер-админ) ---
$router->get('/admin/repository', [\App\Controllers\Admin\RepositoryController::class, 'files']);
$router->post('/admin/repository/upload', [\App\Controllers\Admin\RepositoryController::class, 'upload']);
$router->post('/admin/repository/{id}/delete', [\App\Controllers\Admin\RepositoryController::class, 'destroyFile']);
$router->get('/admin/repository/users', [\App\Controllers\Admin\RepositoryController::class, 'users']);
$router->post('/admin/repository/users/create', [\App\Controllers\Admin\RepositoryController::class, 'storeUser']);
$router->post('/admin/repository/users/{id}/toggle', [\App\Controllers\Admin\RepositoryController::class, 'toggleUser']);
$router->post('/admin/repository/users/{id}/reset-password', [\App\Controllers\Admin\RepositoryController::class, 'resetUserPassword']);
$router->post('/admin/repository/users/{id}/delete', [\App\Controllers\Admin\RepositoryController::class, 'destroyUser']);

// --- Портал файлового хранилища (собственная авторизация, /repo) ---
$router->get('/repo/login', [\App\Controllers\Repo\AuthController::class, 'showLogin']);
$router->post('/repo/login', [\App\Controllers\Repo\AuthController::class, 'login']);
$router->get('/repo/login/2fa', [\App\Controllers\Repo\AuthController::class, 'showTwoFactor']);
$router->post('/repo/login/2fa', [\App\Controllers\Repo\AuthController::class, 'verifyTwoFactor']);
$router->post('/repo/logout', [\App\Controllers\Repo\AuthController::class, 'logout']);
$router->get('/repo', [\App\Controllers\Repo\PortalController::class, 'index']);
$router->get('/repo/download/{id}', [\App\Controllers\Repo\PortalController::class, 'download']);
$router->get('/repo/security', [\App\Controllers\Repo\PortalController::class, 'security']);
$router->post('/repo/security/2fa/enable', [\App\Controllers\Repo\PortalController::class, 'enableTotp']);
$router->post('/repo/security/2fa/disable', [\App\Controllers\Repo\PortalController::class, 'disableTotp']);

// --- Health-check (мониторинг) ---
$router->get('/health', [\App\Controllers\Site\HealthController::class, 'index']);

// --- PWA-манифест ---
$router->get('/manifest.webmanifest', [\App\Controllers\Site\ManifestController::class, 'webmanifest']);

// --- SEO ---
$router->get('/sitemap.xml', [\App\Controllers\Site\SitemapController::class, 'xml']);
$router->get('/robots.txt', [\App\Controllers\Site\SitemapController::class, 'robots']);

// --- Публичный сайт ---
$router->get('/', [SitePageController::class, 'home']);
$router->get('/news', [SiteNewsController::class, 'index']);
$router->get('/projects', [\App\Controllers\Site\ProjectController::class, 'index']);
$router->get('/projects/{slug}', [\App\Controllers\Site\ProjectController::class, 'show']);
$router->get('/news/rss.xml', [SiteNewsController::class, 'feed']);
$router->get('/news/{slug}/photos.zip', [SiteNewsController::class, 'photosZip']);
$router->get('/news/{slug}', [SiteNewsController::class, 'show']);
$router->get('/search', [\App\Controllers\Site\SearchController::class, 'index']);
$router->get('/calendar', [\App\Controllers\Site\CalendarController::class, 'index']);
$router->get('/albums', [\App\Controllers\Site\AlbumController::class, 'index']);
$router->get('/albums/{slug}', [\App\Controllers\Site\AlbumController::class, 'show']);
$router->post('/subscribe', [\App\Controllers\Site\SubscribeController::class, 'subscribe']);
$router->get('/captcha.png', [\App\Controllers\Site\CaptchaController::class, 'image']);
$router->get('/push/key', [\App\Controllers\Site\PushController::class, 'key']);
$router->post('/push/subscribe', [\App\Controllers\Site\PushController::class, 'subscribe']);
$router->post('/push/unsubscribe', [\App\Controllers\Site\PushController::class, 'unsubscribe']);
$router->get('/unsubscribe', [\App\Controllers\Site\SubscribeController::class, 'unsubscribe']);
$router->get('/opendata', [\App\Controllers\Site\OpenDataController::class, 'index']);
$router->get('/opendata/{dataset}', [\App\Controllers\Site\OpenDataController::class, 'dataset']);
$router->get('/catalog/{type}', [\App\Controllers\Site\ContentController::class, 'index']);
$router->get('/catalog/{type}/{slug}', [\App\Controllers\Site\ContentController::class, 'show']);
$router->post('/forms/{slug}/submit', [SiteFormController::class, 'submit']);
$router->get('/{slug}', [SitePageController::class, 'show']);

// Журнал действий администраторов: центральная запись изменяющих запросов
// панели (кто/что/когда/откуда; тело запроса не сохраняется).
if (\App\Core\Session::hasCookie()) {
    \App\Models\AuditLog::record();
}

// После успешных изменений публичного контента сбрасываем файловый кеш и
// Cloudflare. Shutdown-регистрация срабатывает и при redirect/exit контроллера.
\App\Core\PublicResponseCache::registerContentInvalidation();

// Onboarding второго фактора: после корректного пароля пользователь без
// Telegram получает ограниченную сессию и может открыть только профиль,
// настройки доставки кода и выход. Остальная админка остаётся закрытой.
$guardPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if (\App\Core\Session::hasCookie()
    && \App\Core\Auth::check()
    && \App\Core\Auth::requiresTwoFactorSetup()) {
    $allowedSetupPaths = ['/admin/profile', '/admin/settings', '/admin/logout'];
    $allowed = false;
    foreach ($allowedSetupPaths as $allowedPath) {
        if ($guardPath === $allowedPath || str_starts_with($guardPath, $allowedPath . '/')) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed && str_starts_with($guardPath, '/admin')) {
        header('Location: /admin/profile');
        exit;
    }
}

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
