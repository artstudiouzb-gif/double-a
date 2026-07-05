<?php

$pageTitle = 'Дашборд';
$activeNav = 'dashboard';
require __DIR__ . '/layout/header.php';

/** @var array $user */
/** @var array $counts */
?>
<p>Добро пожаловать, <strong><?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?></strong>.</p>

<div class="stat-grid">
    <a href="/admin/news" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['news'] ?></span>
        <span class="stat-card__label">Новостей</span>
    </a>
    <a href="/admin/pages" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['pages'] ?></span>
        <span class="stat-card__label">Страниц</span>
    </a>
    <a href="/admin/projects" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['projects'] ?></span>
        <span class="stat-card__label">Проектов</span>
    </a>
    <a href="/admin/team" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['team'] ?></span>
        <span class="stat-card__label">Сотрудников</span>
    </a>
    <a href="/admin/forms" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['forms'] ?></span>
        <span class="stat-card__label">Форм</span>
    </a>
    <a href="/admin/forms" class="stat-card<?= $counts['submissions_unread'] > 0 ? ' stat-card--highlight' : '' ?>">
        <span class="stat-card__value"><?= (int) $counts['submissions_unread'] ?></span>
        <span class="stat-card__label">Непрочитанных заявок</span>
    </a>
    <a href="/admin/files" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['files'] ?></span>
        <span class="stat-card__label">Файлов</span>
    </a>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
