<?php

use App\Core\Csrf;

$pageTitle = 'Новости';
$activeNav = 'news';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
?>
<div class="page-toolbar">
    <a href="/admin/news/create" class="btn btn--primary">+ Добавить новость</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Заголовок</th>
            <th>Статус</th>
            <th>Дата публикации</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="4" class="data-table__empty">Новостей пока нет.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></td>
                <td>
                    <span class="badge badge--<?= $item['status'] ?>">
                        <?= $item['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?>
                    </span>
                </td>
                <td><?= $item['published_at'] ? htmlspecialchars($item['published_at'], ENT_QUOTES) : '—' ?></td>
                <td class="data-table__actions">
                    <a class="btn btn--small" href="/admin/news/<?= (int) $item['id'] ?>/edit">Редактировать</a>
                    <form method="post" action="/admin/news/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить новость «<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>»?">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../layout/footer.php'; ?>
