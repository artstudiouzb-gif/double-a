<?php

use App\Core\Csrf;
use App\Core\Format;
use App\Models\FileEntry;

$pageTitle = 'Файлы';
$activeNav = 'files';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
?>
<div class="form-card" style="margin-bottom:30px;">
    <form method="post" action="/admin/files/upload" enctype="multipart/form-data" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="file">Файл</label>
            <input type="file" id="file" name="file" required>
        </div>
        <div class="form-field">
            <label for="access_type">Доступ</label>
            <select id="access_type" name="access_type">
                <option value="public">Открытый (прямая ссылка)</option>
                <option value="protected">Защищённый (только по сессии или токену)</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Загрузить</button>
        </div>
    </form>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Имя файла</th>
            <th>Тип</th>
            <th>Размер</th>
            <th>Доступ</th>
            <th>Ссылка</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="6" class="data-table__empty">Файлов пока нет.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['original_name'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($item['mime_type'], ENT_QUOTES) ?></td>
                <td><?= Format::fileSize((int) $item['size']) ?></td>
                <td>
                    <span class="badge badge--<?= $item['access_type'] === 'public' ? 'published' : 'draft' ?>">
                        <?= $item['access_type'] === 'public' ? 'Открытый' : 'Защищённый' ?>
                    </span>
                </td>
                <td style="max-width:260px; word-break:break-all;">
                    <?php if ($item['access_type'] === 'public'): ?>
                        <code><?= htmlspecialchars(FileEntry::publicUrl($item), ENT_QUOTES) ?></code>
                    <?php else: ?>
                        <code>/download.php?file_id=<?= (int) $item['id'] ?>&amp;token=<?= htmlspecialchars($item['access_token'], ENT_QUOTES) ?></code>
                    <?php endif; ?>
                </td>
                <td class="data-table__actions">
                    <?php if ($item['access_type'] === 'protected'): ?>
                        <form method="post" action="/admin/files/<?= (int) $item['id'] ?>/regenerate-token">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn--small">Новый токен</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="/admin/files/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить файл «<?= htmlspecialchars($item['original_name'], ENT_QUOTES) ?>»?">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../layout/footer.php'; ?>
