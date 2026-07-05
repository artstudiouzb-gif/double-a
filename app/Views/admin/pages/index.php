<?php

use App\Core\Csrf;

$pageTitle = 'Страницы';
$activeNav = 'pages';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
?>
<div class="page-toolbar">
    <a href="/admin/pages/create" class="btn btn--primary">+ Добавить страницу</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Заголовок</th>
            <th>URL</th>
            <th>Статус</th>
            <th>Главная</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="5" class="data-table__empty">Страниц пока нет.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></td>
                <td>/<?= htmlspecialchars($item['slug'], ENT_QUOTES) ?></td>
                <td>
                    <span class="badge badge--<?= $item['status'] ?>">
                        <?= $item['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?>
                    </span>
                </td>
                <td><?= $item['is_home'] ? '✓' : '' ?></td>
                <td class="data-table__actions">
                    <a class="btn btn--small" href="/admin/pages/<?= (int) $item['id'] ?>/edit">Редактировать</a>
                    <form method="post" action="/admin/pages/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить страницу «<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>» вместе со всеми блоками?">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../layout/footer.php'; ?>
