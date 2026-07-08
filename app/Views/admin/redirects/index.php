<?php

use App\Core\Csrf;

$pageTitle = 'Редиректы';
$activeNav = 'redirects';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
?>
<p class="form-hint">301/302-редиректы сохраняют посетителей и SEO-вес при переезде со старого сайта: старый адрес автоматически ведёт на новый. Совпадение — по пути (query-строка переносится). Редирект срабатывает раньше страниц, поэтому им можно переопределить и существующий адрес.</p>

<div class="form-card" style="margin-bottom:20px;">
    <h2 style="margin-top:0;">Добавить редирект</h2>
    <form method="post" action="/admin/redirects/create" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="from_path">Старый адрес (откуда)</label>
            <input type="text" id="from_path" name="from_path" placeholder="/old-page или https://asdr.gov.uz/old-page" required>
            <span class="form-hint">Можно вставить полный URL старого сайта — возьмётся только путь.</span>
        </div>
        <div class="form-field">
            <label for="to_url">Новый адрес (куда)</label>
            <input type="text" id="to_url" name="to_url" placeholder="/new-page или https://site.uz/new" required>
        </div>
        <div class="form-field">
            <label for="code">Тип</label>
            <select id="code" name="code">
                <option value="301">301 — постоянный (переезд навсегда, для SEO)</option>
                <option value="302">302 — временный</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Добавить</button>
        </div>
    </form>
</div>

<div class="form-card" style="margin-bottom:20px;">
    <h2 style="margin-top:0;">Массовый импорт</h2>
    <form method="post" action="/admin/redirects/import" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="list">Список (по одному редиректу в строке)</label>
            <textarea id="list" name="list" rows="6" placeholder="/old-1 /new-1&#10;/old-2 -> /new-2 302&#10;https://asdr.gov.uz/page /o-nas"></textarea>
            <span class="form-hint">Формат: «откуда куда [302]», разделитель — пробел или «-&gt;». Строки с «#» — комментарии. Дубли и ошибки пропускаются.</span>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Импортировать</button>
        </div>
    </form>
</div>

<?php if (empty($items)): ?>
    <p class="form-hint">Редиректов пока нет.</p>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Откуда</th><th>Куда</th><th>Тип</th><th>Переходов</th><th>Последний</th><th>Статус</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><code style="font-size:12px;"><?= htmlspecialchars((string) $item['from_path'], ENT_QUOTES) ?></code></td>
                    <td><code style="font-size:12px;"><?= htmlspecialchars((string) $item['to_url'], ENT_QUOTES) ?></code></td>
                    <td><?= (int) $item['code'] ?></td>
                    <td><?= (int) $item['hits'] ?></td>
                    <td style="white-space:nowrap;"><?= htmlspecialchars((string) ($item['last_hit_at'] ?? '—'), ENT_QUOTES) ?></td>
                    <td>
                        <?php if ((int) $item['is_active'] === 1): ?>
                            <span class="badge badge--success">Активен</span>
                        <?php else: ?>
                            <span class="badge">Выключен</span>
                        <?php endif; ?>
                    </td>
                    <td class="data-table__actions" style="white-space:nowrap;">
                        <form method="post" action="/admin/redirects/<?= (int) $item['id'] ?>/toggle" style="display:inline;">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="active" value="<?= (int) $item['is_active'] === 1 ? '0' : '1' ?>">
                            <button type="submit" class="btn btn--small"><?= (int) $item['is_active'] === 1 ? 'Выключить' : 'Включить' ?></button>
                        </form>
                        <form method="post" action="/admin/redirects/<?= (int) $item['id'] ?>/delete" style="display:inline;" data-confirm="Удалить редирект «<?= htmlspecialchars((string) $item['from_path'], ENT_QUOTES) ?>»?">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
