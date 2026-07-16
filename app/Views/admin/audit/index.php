<?php

$pageTitle = 'Журнал действий';
$activeNav = 'audit';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var array $filters */
/** @var array $actors */

// Человекочитаемая метка раздела панели по началу пути.
$sectionLabels = [
    '/admin/pages' => 'Страницы',
    '/admin/news' => 'Новости',
    '/admin/projects' => 'Проекты',
    '/admin/team' => 'Команда',
    '/admin/forms' => 'Формы',
    '/admin/files' => 'Файлы',
    '/admin/trash' => 'Корзина',
    '/admin/design' => 'Дизайн',
    '/admin/menu' => 'Меню',
    '/admin/widgets' => 'Виджеты',
    '/admin/header' => 'Шапка сайта',
    '/admin/languages' => 'Языки',
    '/admin/content-types' => 'Типы контента',
    '/admin/content' => 'Контент',
    '/admin/social' => 'Соцсети',
    '/admin/webhooks' => 'Вебхуки',
    '/admin/settings' => 'Настройки',
    '/admin/users' => 'Пользователи',
    '/admin/profile' => 'Профиль',
    '/admin/repository' => 'Хранилище',
    '/admin/backup' => 'Бэкапы',
    '/admin/settings/demo-content' => 'Демо-контент',
    '/admin/logout' => 'Выход',
];
$sectionOf = static function (string $path) use ($sectionLabels): string {
    foreach ($sectionLabels as $prefix => $label) {
        if (str_starts_with($path, $prefix)) {
            return $label;
        }
    }
    return 'Панель';
};

// Query-строка текущих фильтров для ссылок пагинации.
$qs = static function (int $p) use ($filters): string {
    $params = array_filter([
        'user_id' => $filters['user_id'] ?: null,
        'q' => $filters['q'] !== '' ? $filters['q'] : null,
        'from' => $filters['from'] !== '' ? $filters['from'] : null,
        'to' => $filters['to'] !== '' ? $filters['to'] : null,
        'page' => $p > 1 ? $p : null,
    ]);
    return $params === [] ? '/admin/audit' : '/admin/audit?' . http_build_query($params);
};
?>
<div style="display:flex;gap:8px;margin-bottom:14px;">
    <a class="btn btn--small btn--primary" href="/admin/audit">Действия администраторов</a>
    <a class="btn btn--small" href="/admin/audit/errors">Ошибки сайта</a>
</div>

<p class="form-hint">Все изменяющие действия администраторов в панели: кто, что, когда и с какого IP. Входы/выходы и события безопасности дополнительно пишутся в security-лог. Записи старше 180 дней удаляются автоматически.</p>

<form method="get" action="/admin/audit" class="form-grid form-grid--inline" style="margin-bottom:18px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
    <div class="form-field" style="margin:0;">
        <label for="f_user">Администратор</label>
        <select id="f_user" name="user_id">
            <option value="">— все —</option>
            <?php foreach ($actors as $actor): ?>
                <option value="<?= (int) $actor['user_id'] ?>" <?= $filters['user_id'] === (int) $actor['user_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $actor['username'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-field" style="margin:0;">
        <label for="f_q">Путь содержит</label>
        <input type="text" id="f_q" name="q" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES) ?>" placeholder="например: pages">
    </div>
    <div class="form-field" style="margin:0;">
        <label for="f_from">С даты</label>
        <input type="date" id="f_from" name="from" value="<?= htmlspecialchars($filters['from'], ENT_QUOTES) ?>">
    </div>
    <div class="form-field" style="margin:0;">
        <label for="f_to">По дату</label>
        <input type="date" id="f_to" name="to" value="<?= htmlspecialchars($filters['to'], ENT_QUOTES) ?>">
    </div>
    <div class="form-actions" style="margin:0;">
        <button type="submit" class="btn btn--primary">Фильтровать</button>
        <a href="/admin/audit" class="btn">Сбросить</a>
    </div>
</form>

<p class="form-hint">Найдено записей: <strong><?= (int) $total ?></strong></p>

<?php if (empty($items)): ?>
    <p class="form-hint">Записей нет. Журнал наполняется по мере работы администраторов в панели.</p>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Когда</th><th>Кто</th><th>Раздел</th><th>Действие</th><th>IP</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= htmlspecialchars((string) $item['created_at'], ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars((string) $item['username'], ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars($sectionOf((string) $item['path']), ENT_QUOTES) ?></td>
                    <td><code style="font-size:12px;"><?= htmlspecialchars($item['method'] . ' ' . $item['path'], ENT_QUOTES) ?></code></td>
                    <td><?= htmlspecialchars((string) ($item['ip'] ?? '—'), ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
        <div style="display:flex;gap:8px;align-items:center;margin-top:16px;">
            <?php if ($page > 1): ?><a class="btn btn--small" href="<?= htmlspecialchars($qs($page - 1), ENT_QUOTES) ?>">← Новее</a><?php endif; ?>
            <span class="form-hint">Страница <?= (int) $page ?> из <?= (int) $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn--small" href="<?= htmlspecialchars($qs($page + 1), ENT_QUOTES) ?>">Старее →</a><?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
