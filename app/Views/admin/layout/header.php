<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;

/** @var string $pageTitle */
/** @var string $activeNav */

$navIsSuper = Auth::isSuperAdmin();
$navUser = Auth::user();

// Иконки разделов (инлайновый SVG, currentColor, без внешних зависимостей).
$navIcon = static function (string $name): string {
    $p = [
        'dashboard' => '<path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/>',
        'news' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/>',
        'pages' => '<path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/>',
        'projects' => '<path d="M3 7h7l2 2h9v11H3z"/>',
        'team' => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3 3-5 6-5s6 2 6 5"/><path d="M17 8a3 3 0 0 1 0 6"/>',
        'forms' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'files' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M5 18l5-5 4 4 3-3 2 2"/>',
        'trash' => '<path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'widgets' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'header' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/>',
        'footer' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 15h18"/>',
        'performance' => '<path d="M13 2 3 14h7l-1 8 10-12h-7z"/>',
        'languages' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
        'users' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'content_types' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'social' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.5 13.5l7 4M15.5 6.5l-7 4"/>',
        'webhooks' => '<path d="M13 3L4 14h7l-1 7 9-11h-7z"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M4 12a8 8 0 0 1 .2-1.8L2 8.5l2 3.5M20 12a8 8 0 0 0-.2-1.8"/><path d="M12 2v3M12 19v3M4.2 6.2l2.1 2.1M17.7 15.7l2.1 2.1M2 12h3M19 12h3M6.3 17.7l-2.1 2.1M19.8 6.2l-2.1 2.1"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'repository' => '<path d="M12 2 4 6v6c0 5 3.4 7.7 8 10 4.6-2.3 8-5 8-10V6l-8-4Z"/><path d="m9 12 2 2 4-4"/>',
        'design' => '<circle cx="13.5" cy="6.5" r="2.5"/><circle cx="6.5" cy="12" r="2.5"/><circle cx="17" cy="14" r="2.5"/><path d="M12 22a10 10 0 1 1 10-10c0 2-1.5 3-3 3h-2a2 2 0 0 0-1 3.7A2 2 0 0 1 12 22Z"/>',
        'albums' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m5 17 4-4 3 3 3-3 4 4"/>',
        'videos' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/>',
        'redirects' => '<path d="M4 7h10a5 5 0 0 1 5 5v5"/><path d="m15 13 4 4 4-4"/>',
        'subscribers' => '<path d="M4 5h16v14H4z"/><path d="m4 7 8 6 8-6"/>',
        'audit' => '<path d="M9 3h6l1 3h3v15H5V6h3z"/><path d="M9 11h6M9 15h4"/>',
    ];
    $body = $p[$name] ?? '<circle cx="12" cy="12" r="9"/>';

    return '<svg class="admin-nav-item__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $body . '</svg>';
};

// --- Разделы навигации, сгруппированные по секциям (стиль Statamic) ---
// ВАЖНО: все переменные заголовка имеют префикс nav*/tb*, чтобы не затирать
// переменные вьюхи (header.php подключается в общую область видимости).
$navContent = [
    'news' => ['/admin/news', 'Новости'],
    'pages' => ['/admin/pages', 'Страницы'],
    'projects' => ['/admin/projects', 'Проекты'],
    'team' => ['/admin/team', 'Команда'],
];
try {
    foreach (\App\Models\ContentType::all() as $navCt) {
        $navContent['content:' . $navCt['slug']] = ['/admin/content/' . $navCt['slug'], $navCt['name']];
    }
} catch (\Throwable $e) {
    // миграция типов контента не накатана — пропускаем
}

$navTools = [
    'albums' => ['/admin/albums', 'Фотоальбомы'],
    'videos' => ['/admin/videos', 'Видео'],
    'forms' => ['/admin/forms', 'Формы'],
    'files' => ['/admin/files', 'Файлы'],
    'trash' => ['/admin/trash', 'Корзина'],
];

// «Настройки» из 12 пунктов подряд читались как хаос — разбиты на две группы:
// «Внешний вид» (то, что видно на сайте) и «Система» (интеграции и служебное).
$navAppearance = [];
$navSystem = [];
$navUsersGroup = [];
if ($navIsSuper) {
    $navAppearance = [
        'design' => ['/admin/design', 'Дизайн'],
        'menu' => ['/admin/menu', 'Меню'],
        'widgets' => ['/admin/widgets', 'Виджеты'],
        'header' => ['/admin/header', 'Шапка сайта'],
        'footer' => ['/admin/footer', 'Подвал сайта'],
    ];
    $navSystem = [
        'languages' => ['/admin/languages', 'Языки'],
        'content_types' => ['/admin/content-types', 'Типы контента'],
        'telegram' => ['/admin/telegram', 'Telegram'],
        'social' => ['/admin/social', 'Соцсети'],
        'webhooks' => ['/admin/webhooks', 'Вебхуки'],
        'redirects' => ['/admin/redirects', 'Редиректы'],
        'performance' => ['/admin/performance', 'Производительность'],
        'settings' => ['/admin/settings', 'Настройки'],
    ];
    $navUsersGroup = [
        'users' => ['/admin/users', 'Пользователи'],
        'audit' => ['/admin/audit', 'Журнал действий'],
    ];
    $navTools['subscribers'] = ['/admin/subscribers', 'Подписчики'];
    $navTools['repository'] = ['/admin/repository', 'Хранилище'];
}

$navGroups = [
    'Контент' => $navContent,
    'Инструменты' => $navTools,
    'Внешний вид' => $navAppearance,
    'Система' => $navSystem,
    'Пользователи' => $navUsersGroup,
];

// Крошка в топбаре: «Группа › Раздел» вместо дубля заголовка страницы (h1 ниже).
$navCrumb = $pageTitle;
foreach ($navGroups as $navGroupLabel => $navGroupItems) {
    if (isset($navGroupItems[$activeNav])) {
        $navCrumb = $navGroupLabel . ' › ' . $navGroupItems[$activeNav][1];
        break;
    }
}

$navInitials = mb_strtoupper(mb_substr((string) ($navUser['username'] ?? 'A'), 0, 1));
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?> — <?= htmlspecialchars(\App\Core\AdminBrand::name(), ENT_QUOTES) ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/admin.css'), ENT_QUOTES) ?>">
<?= \App\Core\AdminBrand::styleTag() ?>
<script nonce="<?= \App\Core\SecurityHeaders::nonce() ?>">
try {
    if (localStorage.getItem('artstudio:admin-sidebar-collapsed') === '1') {
        document.documentElement.classList.add('admin-nav-collapsed');
    }
} catch (e) {}
</script>
</head>
<body class="admin-body">
<a class="admin-skip-link" href="#admin-content">Перейти к содержимому</a>
<header class="admin-topbar">
    <button type="button" class="admin-topbar__toggle" data-sidebar-toggle aria-label="Открыть меню" aria-controls="admin-sidebar" aria-expanded="false">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <a href="/admin" class="admin-topbar__brand">
        <?= \App\Core\AdminBrand::badgeHtml() ?>
        <span class="admin-topbar__name"><?= htmlspecialchars(\App\Core\AdminBrand::name(), ENT_QUOTES) ?></span>
    </a>
    <span class="admin-topbar__crumb"><?= htmlspecialchars($navCrumb, ENT_QUOTES) ?></span>
    <div class="admin-topbar__spacer"></div>
    <div class="admin-search" data-search>
        <svg class="admin-search__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" class="admin-search__input" data-search-input
               placeholder="Поиск…" autocomplete="off" aria-label="Поиск по админке">
        <kbd class="admin-search__kbd">Ctrl K</kbd>
        <div class="admin-search__results" data-search-results hidden></div>
    </div>

    <div class="admin-tools">
        <details class="admin-menu admin-tools__quick">
            <summary class="admin-tbtn admin-tbtn--primary" aria-label="Быстрые действия" title="Быстрые действия">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                <span class="admin-tbtn__label">Создать</span>
            </summary>
            <div class="admin-menu__list">
                <div class="admin-menu__label">Быстрые действия</div>
                <a href="/admin/news/create" class="admin-user__link">Новость</a>
                <a href="/admin/pages/create" class="admin-user__link">Страницу</a>
                <a href="/admin/projects/create" class="admin-user__link">Проект</a>
                <a href="/admin/team/create" class="admin-user__link">Сотрудника</a>
                <a href="/admin/albums" class="admin-user__link">Фотоальбом</a>
                <a href="/admin/forms/create" class="admin-user__link">Форму</a>
                <?php foreach ($navContent as $navKey => [$navUrl, $navText]): ?>
                    <?php if (str_starts_with($navKey, 'content:')): ?>
                        <a href="<?= $navUrl ?>/create" class="admin-user__link"><?= htmlspecialchars($navText, ENT_QUOTES) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </details>

        <?php if ($navIsSuper): ?>
        <form method="post" action="/admin/performance/clear-cache" class="admin-tools__form">
            <?= Csrf::field() ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin', ENT_QUOTES) ?>">
            <button type="submit" class="admin-tbtn" title="Сбросить кэш страниц">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/></svg>
                <span class="admin-tbtn__label">Сброс кэша</span>
            </button>
        </form>
        <?php endif; ?>

        <a href="/" target="_blank" rel="noopener" class="admin-tbtn" title="Открыть сайт в новой вкладке">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg>
            <span class="admin-tbtn__label">Сайт</span>
        </a>
    </div>

    <details class="admin-user">
        <summary class="admin-user__btn" aria-label="Аккаунт">
            <span class="admin-user__avatar"><?= htmlspecialchars($navInitials, ENT_QUOTES) ?></span>
        </summary>
        <div class="admin-user__menu">
            <div class="admin-user__name"><?= htmlspecialchars((string) ($navUser['username'] ?? ''), ENT_QUOTES) ?></div>
            <a href="/admin/profile" class="admin-user__link">Профиль</a>
            <form method="post" action="/admin/logout">
                <?= Csrf::field() ?>
                <button type="submit" class="admin-user__link admin-user__logout">Выйти</button>
            </form>
        </div>
    </details>
</header>

<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" data-sidebar aria-label="Основная навигация">
        <a href="/admin" class="admin-nav-item <?= $activeNav === 'dashboard' ? 'is-active' : '' ?>" title="Дашборд"<?= $activeNav === 'dashboard' ? ' aria-current="page"' : '' ?>>
            <?= $navIcon('dashboard') ?><span>Дашборд</span>
        </a>
        <?php foreach ($navGroups as $navGroupLabel => $navGroupItems): ?>
            <?php if (empty($navGroupItems)) { continue; } ?>
            <div class="admin-nav-group" data-nav-group="<?= htmlspecialchars($navGroupLabel, ENT_QUOTES) ?>">
                <button type="button" class="admin-sidebar__label admin-sidebar__label--toggle" data-nav-toggle aria-expanded="true">
                    <span><?= htmlspecialchars($navGroupLabel, ENT_QUOTES) ?></span>
                    <svg class="admin-sidebar__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="admin-nav-group__items">
                    <?php foreach ($navGroupItems as $navKey => [$navUrl, $navText]): ?>
                        <?php $navIc = str_starts_with($navKey, 'content:') ? 'content_types' : $navKey; ?>
                        <a href="<?= $navUrl ?>" class="admin-nav-item <?= $activeNav === $navKey ? 'is-active' : '' ?>" title="<?= htmlspecialchars($navText, ENT_QUOTES) ?>"<?= $activeNav === $navKey ? ' aria-current="page"' : '' ?>>
                            <?= $navIcon($navIc) ?><span><?= htmlspecialchars($navText, ENT_QUOTES) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="admin-sidebar__label">Аккаунт</div>
        <a href="/admin/profile" class="admin-nav-item <?= $activeNav === 'profile' ? 'is-active' : '' ?>" title="Профиль"<?= $activeNav === 'profile' ? ' aria-current="page"' : '' ?>>
            <?= $navIcon('profile') ?><span>Профиль</span>
        </a>
        <button type="button" class="admin-sidebar__collapse" data-sidebar-collapse aria-expanded="true" title="Свернуть меню">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 18-6-6 6-6"/><path d="M20 5v14"/></svg>
            <span>Свернуть меню</span>
        </button>
    </aside>
    <script nonce="<?= \App\Core\SecurityHeaders::nonce() ?>">
    (function () {
        'use strict';
        var KEY = 'admin_nav_collapsed';
        var collapsed = [];
        try { collapsed = JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (e) {}
        var groups = document.querySelectorAll('.admin-nav-group');
        function save() {
            var set = [];
            document.querySelectorAll('.admin-nav-group.is-collapsed').forEach(function (x) {
                set.push(x.getAttribute('data-nav-group'));
            });
            try { localStorage.setItem(KEY, JSON.stringify(set)); } catch (e) {}
        }
        groups.forEach(function (g) {
            var name = g.getAttribute('data-nav-group');
            var btn = g.querySelector('[data-nav-toggle]');
            if (collapsed.indexOf(name) >= 0) {
                g.classList.add('is-collapsed');
                if (btn) { btn.setAttribute('aria-expanded', 'false'); }
            }
            if (btn) {
                btn.addEventListener('click', function () {
                    var isColl = g.classList.toggle('is-collapsed');
                    btn.setAttribute('aria-expanded', isColl ? 'false' : 'true');
                    save();
                });
            }
        });
    })();
    </script>
    <button type="button" class="admin-sidebar-backdrop" data-sidebar-backdrop aria-label="Закрыть меню" tabindex="-1"></button>

    <main class="admin-main" id="admin-content" tabindex="-1">
        <?php foreach (Flash::pull() as $flash): ?>
            <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES) ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES) ?>
            </div>
        <?php endforeach; ?>
        <div class="admin-main__header">
            <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></h1>
            <?php if (!empty($pageActions)): ?>
                <div class="admin-main__actions"><?= $pageActions ?></div>
            <?php endif; ?>
        </div>
