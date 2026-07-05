<?php

use App\Core\Csrf;

/** @var array|null $user */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Панель управления</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-sidebar__brand">ArtStudio CMS</div>
        <nav>
            <a href="/admin" class="is-active">Дашборд</a>
            <a href="/admin/news">Новости</a>
            <a href="/admin/pages">Страницы</a>
            <a href="/admin/projects">Проекты</a>
            <a href="/admin/team">Команда</a>
            <a href="/admin/forms">Формы</a>
            <a href="/admin/files">Файлы</a>
            <a href="/admin/settings">Настройки</a>
        </nav>
        <form method="post" action="/admin/logout" class="admin-sidebar__logout">
            <?= Csrf::field() ?>
            <button type="submit">Выйти</button>
        </form>
    </aside>
    <main class="admin-main">
        <h1>Добро пожаловать, <?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?></h1>
        <p>Разделы CRUD (Новости, Страницы, Проекты, Команда, Формы, Файлы, Настройки) добавляются на следующем этапе.</p>
    </main>
</div>
</body>
</html>
