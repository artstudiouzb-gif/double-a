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
        'languages' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
        'users' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'content_types' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'social' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.5 13.5l7 4M15.5 6.5l-7 4"/>',
        'webhooks' => '<path d="M13 3L4 14h7l-1 7 9-11h-7z"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M4 12a8 8 0 0 1 .2-1.8L2 8.5l2 3.5M20 12a8 8 0 0 0-.2-1.8"/><path d="M12 2v3M12 19v3M4.2 6.2l2.1 2.1M17.7 15.7l2.1 2.1M2 12h3M19 12h3M6.3 17.7l-2.1 2.1M19.8 6.2l-2.1 2.1"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'repository' => '<path d="M12 2 4 6v6c0 5 3.4 7.7 8 10 4.6-2.3 8-5 8-10V6l-8-4Z"/><path d="m9 12 2 2 4-4"/>',
        'design' => '<circle cx="13.5" cy="6.5" r="2.5"/><circle cx="6.5" cy="12" r="2.5"/><circle cx="17" cy="14" r="2.5"/><path d="M12 22a10 10 0 1 1 10-10c0 2-1.5 3-3 3h-2a2 2 0 0 0-1 3.7A2 2 0 0 1 12 22Z"/>',
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
    'forms' => ['/admin/forms', 'Формы'],
    'files' => ['/admin/files', 'Файлы'],
    'trash' => ['/admin/trash', 'Корзина'],
];

$navSettings = [];
$navUsersGroup = [];
if ($navIsSuper) {
    $navSettings = [
        'design' => ['/admin/design', 'Дизайн'],
        'menu' => ['/admin/menu', 'Меню'],
        'widgets' => ['/admin/widgets', 'Виджеты'],
        'header' => ['/admin/header', 'Шапка сайта'],
        'languages' => ['/admin/languages', 'Языки'],
        'content_types' => ['/admin/content-types', 'Типы контента'],
        'social' => ['/admin/social', 'Соцсети'],
        'webhooks' => ['/admin/webhooks', 'Вебхуки'],
        'redirects' => ['/admin/redirects', 'Редиректы'],
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
    'Настройки' => $navSettings,
    'Пользователи' => $navUsersGroup,
];

$navInitials = mb_strtoupper(mb_substr((string) ($navUser['username'] ?? 'A'), 0, 1));
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?> — Панель управления</title>
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/admin.css'), ENT_QUOTES) ?>">
</head>
<body class="admin-body">
<header class="admin-topbar">
    <button type="button" class="admin-topbar__toggle" data-sidebar-toggle aria-label="Меню">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <a href="/admin" class="admin-topbar__brand">
        <span class="admin-topbar__logo">A</span>
        <span class="admin-topbar__name">ArtStudio</span>
    </a>
    <span class="admin-topbar__crumb"><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></span>
    <div class="admin-topbar__spacer"></div>
    <div class="admin-search" data-search>
        <svg class="admin-search__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" class="admin-search__input" data-search-input
               placeholder="Поиск…" autocomplete="off" aria-label="Поиск по админке">
        <kbd class="admin-search__kbd">Ctrl K</kbd>
        <div class="admin-search__results" data-search-results hidden></div>
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
    <aside class="admin-sidebar" data-sidebar>
        <a href="/admin" class="admin-nav-item <?= $activeNav === 'dashboard' ? 'is-active' : '' ?>">
            <?= $navIcon('dashboard') ?><span>Дашборд</span>
        </a>
        <?php foreach ($navGroups as $navGroupLabel => $navGroupItems): ?>
            <?php if (empty($navGroupItems)) { continue; } ?>
            <div class="admin-sidebar__label"><?= htmlspecialchars($navGroupLabel, ENT_QUOTES) ?></div>
            <?php foreach ($navGroupItems as $navKey => [$navUrl, $navText]): ?>
                <?php $navIc = str_starts_with($navKey, 'content:') ? 'content_types' : $navKey; ?>
                <a href="<?= $navUrl ?>" class="admin-nav-item <?= $activeNav === $navKey ? 'is-active' : '' ?>">
                    <?= $navIcon($navIc) ?><span><?= htmlspecialchars($navText, ENT_QUOTES) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <div class="admin-sidebar__label">Аккаунт</div>
        <a href="/admin/profile" class="admin-nav-item <?= $activeNav === 'profile' ? 'is-active' : '' ?>">
            <?= $navIcon('profile') ?><span>Профиль</span>
        </a>
    </aside>

    <main class="admin-main">
        <?php foreach (Flash::pull() as $flash): ?>
            <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES) ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES) ?>
            </div>
        <?php endforeach; ?>
        <div class="admin-main__header">
            <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></h1>
        </div>
