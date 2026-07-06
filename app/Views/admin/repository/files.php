<?php

use App\Core\Csrf;
use App\Core\Format;

$pageTitle = 'Хранилище: файлы';
$activeNav = 'repository';
require __DIR__ . '/../layout/header.php';

/** @var array $files */
/** @var string $query */
/** @var list<string> $categories */
?>
<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="/admin/repository" class="btn btn--small btn--primary">Файлы</a>
    <a href="/admin/repository/users" class="btn btn--small">Пользователи портала</a>
</div>
<p class="form-hint">Защищённое файловое хранилище с отдельной авторизацией (портал <code>/repo</code>). Файлы видят все активные пользователи портала; загружает и удаляет только администратор.</p>

<div class="form-card" style="margin-bottom:24px;">
    <h2 style="margin-top:0;">Загрузить файл</h2>
    <form method="post" action="/admin/repository/upload" enctype="multipart/form-data" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="title">Название</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div class="form-field">
            <label for="category">Категория (необязательно)</label>
            <input type="text" id="category" name="category" list="repo-cats" placeholder="напр. Приказы, Отчёты">
            <datalist id="repo-cats">
                <?php foreach ($categories as $cat): ?><option value="<?= htmlspecialchars($cat, ENT_QUOTES) ?>"><?php endforeach; ?>
            </datalist>
        </div>
        <div class="form-field">
            <label for="description">Описание (необязательно)</label>
            <textarea id="description" name="description" rows="2"></textarea>
        </div>
        <div class="form-field">
            <label for="file">Файл (PDF, Office, изображения, ZIP — до 100 МБ)</label>
            <input type="file" id="file" name="file" required>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn--primary">Загрузить</button></div>
    </form>
</div>

<form method="get" action="/admin/repository" style="margin-bottom:16px;display:flex;gap:8px;max-width:420px;">
    <input type="text" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>" placeholder="Поиск по файлам" style="flex:1;">
    <button type="submit" class="btn btn--small">Найти</button>
    <?php if ($query !== ''): ?><a href="/admin/repository" class="btn btn--small">Сброс</a><?php endif; ?>
</form>

<table class="data-table">
    <thead>
        <tr><th>Название</th><th>Категория</th><th>Файл</th><th>Размер</th><th>Скачиваний</th><th>Добавлен</th><th></th></tr>
    </thead>
    <tbody>
        <?php if (empty($files)): ?>
            <tr><td colspan="7" style="text-align:center;color:#888;padding:24px;">Файлов пока нет.</td></tr>
        <?php else: ?>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars((string) $f['title'], ENT_QUOTES) ?></strong>
                        <?php if (!empty($f['description'])): ?><div class="form-hint"><?= htmlspecialchars((string) $f['description'], ENT_QUOTES) ?></div><?php endif; ?>
                    </td>
                    <td><?= $f['category'] !== '' ? htmlspecialchars((string) $f['category'], ENT_QUOTES) : '—' ?></td>
                    <td class="form-hint"><?= htmlspecialchars((string) $f['original_name'], ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars(Format::fileSize((int) $f['size']), ENT_QUOTES) ?></td>
                    <td><?= (int) $f['download_count'] ?></td>
                    <td><?= htmlspecialchars(date('d.m.Y', strtotime((string) $f['created_at'])), ENT_QUOTES) ?></td>
                    <td class="data-table__actions">
                        <form method="post" action="/admin/repository/<?= (int) $f['id'] ?>/delete" data-confirm="Удалить файл «<?= htmlspecialchars((string) $f['title'], ENT_QUOTES) ?>»?">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../layout/footer.php'; ?>
