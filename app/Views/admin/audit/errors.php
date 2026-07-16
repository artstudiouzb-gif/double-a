<?php

use App\Core\Csrf;

$pageTitle = 'Журнал ошибок';
$activeNav = 'audit';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var array $filters */

// Query-строка текущих фильтров для ссылок пагинации.
$qs = static function (int $p) use ($filters): string {
    $params = array_filter([
        'level' => $filters['level'] !== '' ? $filters['level'] : null,
        'q' => $filters['q'] !== '' ? $filters['q'] : null,
        'page' => $p > 1 ? $p : null,
    ]);
    return $params === [] ? '/admin/audit/errors' : '/admin/audit/errors?' . http_build_query($params);
};

// Короткий путь файла без корня проекта — чтобы колонка «Где» читалась.
$shortFile = static function (string $file): string {
    $root = str_replace('\\', '/', dirname(__DIR__, 3)) . '/';
    return str_replace([$root, '\\'], ['', '/'], str_replace('\\', '/', $file));
};
?>
<div style="display:flex;gap:8px;margin-bottom:14px;">
    <a class="btn btn--small" href="/admin/audit">Действия администраторов</a>
    <a class="btn btn--small btn--primary" href="/admin/audit/errors">Ошибки сайта</a>
</div>

<p class="form-hint">Ошибки, перехваченные на сайте и в панели: что случилось, где и почему — понятным языком. Технические детали раскрываются по клику. Записи старше <?= (int) \App\Models\ErrorLog::RETENTION_DAYS ?> дней удаляются автоматически, либо очистите журнал вручную.</p>

<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;margin-bottom:18px;">
    <form method="get" action="/admin/audit/errors" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <div class="form-field" style="margin:0;">
            <label for="f_level">Уровень</label>
            <select id="f_level" name="level">
                <option value="">— все —</option>
                <?php foreach (['ERROR' => 'Ошибка', 'CRITICAL' => 'Критическая'] as $lv => $lvLabel): ?>
                    <option value="<?= $lv ?>" <?= strtoupper($filters['level']) === $lv ? 'selected' : '' ?>><?= $lvLabel ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field" style="margin:0;">
            <label for="f_q">Текст, файл или адрес содержит</label>
            <input type="text" id="f_q" name="q" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES) ?>" placeholder="например: SQLSTATE или /news">
        </div>
        <div class="form-actions" style="margin:0;">
            <button type="submit" class="btn btn--primary">Фильтровать</button>
            <a href="/admin/audit/errors" class="btn">Сбросить</a>
        </div>
    </form>
    <?php if ($total > 0): ?>
        <form method="post" action="/admin/audit/errors/clear" style="margin:0;" data-confirm="Очистить журнал ошибок полностью?">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn--danger">Очистить журнал</button>
        </form>
    <?php endif; ?>
</div>

<p class="form-hint">Найдено записей: <strong><?= (int) $total ?></strong></p>

<?php if (empty($items)): ?>
    <p class="form-hint">Ошибок нет — сайт работает штатно. Записи появляются здесь автоматически, когда на сайте или в панели что-то ломается.</p>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Когда</th><th>Уровень</th><th>Что случилось и почему</th><th>Где</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td style="white-space:nowrap;vertical-align:top;"><?= htmlspecialchars((string) $item['created_at'], ENT_QUOTES) ?></td>
                    <td style="vertical-align:top;">
                        <?php $critical = strtoupper((string) $item['level']) === 'CRITICAL'; ?>
                        <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600;<?= $critical ? 'background:#fde8e8;color:#b42318;' : 'background:#fef0c7;color:#93540b;' ?>">
                            <?= $critical ? 'Критическая' : 'Ошибка' ?>
                        </span>
                    </td>
                    <td style="vertical-align:top;">
                        <?= htmlspecialchars((string) $item['human'], ENT_QUOTES) ?>
                        <details style="margin-top:6px;">
                            <summary style="cursor:pointer;font-size:12px;color:#666;">Технические детали</summary>
                            <code style="display:block;margin-top:6px;font-size:12px;white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars((string) $item['message'], ENT_QUOTES) ?></code>
                        </details>
                    </td>
                    <td style="vertical-align:top;font-size:12px;">
                        <?php if ((string) $item['file'] !== ''): ?>
                            <code style="font-size:12px;word-break:break-all;"><?= htmlspecialchars($shortFile((string) $item['file']) . ':' . (int) $item['line'], ENT_QUOTES) ?></code><br>
                        <?php endif; ?>
                        <span style="color:#666;">Страница: <?= htmlspecialchars((string) $item['url'], ENT_QUOTES) ?></span>
                        <?php if (!empty($item['ip'])): ?><br><span style="color:#666;">IP: <?= htmlspecialchars((string) $item['ip'], ENT_QUOTES) ?></span><?php endif; ?>
                    </td>
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
