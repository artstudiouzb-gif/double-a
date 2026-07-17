<?php

use App\Core\Csrf;
use App\Models\Language;

$pageTitle = 'Команда';
$activeNav = 'team';
$pageActions = '<a href="/admin/team/create" class="btn btn--primary">+ Добавить сотрудника</a>';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
$langs = Language::active();
?>

<table class="data-table">
    <thead>
        <tr>
            <th>Имя</th>
            <th>Должность</th>
            <th>Языки</th>
            <th>Статус</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="5" class="data-table__empty">Сотрудников пока нет.<br><a href="/admin/team/create" class="btn btn--small">+ Добавить первого сотрудника</a></td></tr>
        <?php endif; ?>
        <?php
        // Языки контента для всех строк одним запросом (без N+1).
        $langMap = \App\Models\TeamMember::availableLangsForIds(array_map(static fn ($i): int => (int) $i['id'], $items));
        $siteLangs = array_map(static fn (array $l): string => (string) $l['code'], $langs);
        ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($item['position'] ?? '', ENT_QUOTES) ?></td>
                <td style="white-space:nowrap;"><?= \App\Core\View::renderPartial('admin/layout/lang_badges', ['siteLangs' => $siteLangs, 'has' => $langMap[(int) $item['id']] ?? []]) ?></td>
                <td>
                    <span class="badge badge--<?= $item['status'] ?>">
                        <?= $item['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?>
                    </span>
                </td>
                <td class="data-table__actions">
                    <a class="btn btn--small" href="/admin/team/<?= (int) $item['id'] ?>/edit">Редактировать</a>
                    <form method="post" action="/admin/team/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить сотрудника «<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>»?">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../layout/footer.php'; ?>
