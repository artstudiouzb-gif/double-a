<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;

/** @var string $pageTitle */
/** @var string $activeNav */

$isSuper = Auth::isSuperAdmin();

// Разделы, доступные редактору (управление контентом).
$navItems = [
    'dashboard' => ['/admin', 'Дашборд'],
    'news' => ['/admin/news', 'Новости'],
    'pages' => ['/admin/pages', 'Страницы'],
    'projects' => ['/admin/projects', 'Проекты'],
    'team' => ['/admin/team', 'Команда'],
    'forms' => ['/admin/forms', 'Формы'],
    'files' => ['/admin/files', 'Файлы'],
    'trash' => ['/admin/trash', 'Корзина'],
    'profile' => ['/admin/profile', 'Профиль'],
];

// Системные разделы — только для супер-администратора.
if ($isSuper) {
    $navItems += [
        'menu' => ['/admin/menu', 'Меню'],
        'widgets' => ['/admin/widgets', 'Виджеты'],
        'header' => ['/admin/header', 'Шапка сайта'],
        'languages' => ['/admin/languages', 'Языки'],
        'users' => ['/admin/users', 'Пользователи'],
        'social' => ['/admin/social', 'Соцсети'],
        'settings' => ['/admin/settings', 'Настройки'],
    ];
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?> — Панель управления</title>
<link rel="stylesheet" href="/assets/vendor/pico/pico.classless.min.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-sidebar__brand">ArtStudio CMS</div>
        <nav>
            <?php foreach ($navItems as $key => [$url, $label]): ?>
                <a href="<?= $url ?>" class="<?= $activeNav === $key ? 'is-active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="post" action="/admin/logout" class="admin-sidebar__logout">
            <?= Csrf::field() ?>
            <button type="submit">Выйти</button>
        </form>
    </aside>
    <main class="admin-main">
        <?php foreach (Flash::pull() as $flash): ?>
            <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES) ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES) ?>
            </div>
        <?php endforeach; ?>
        <div class="admin-main__header">
            <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></h1>
            <div class="admin-search" data-search>
                <input type="search" class="admin-search__input" data-search-input
                       placeholder="Поиск… (Ctrl+K)" autocomplete="off" aria-label="Поиск по админке">
                <div class="admin-search__results" data-search-results hidden></div>
            </div>
        </div>
