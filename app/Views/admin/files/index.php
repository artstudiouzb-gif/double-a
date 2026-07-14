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

<div class="form-card" style="margin-bottom:30px;">
    <h2 style="margin-top:0;">Загрузка больших файлов (по частям)</h2>
    <p class="form-hint">Для больших файлов (видео, PDF-презентации) — загрузка чанками в обход ограничений хостинга. До 200 МБ.</p>
    <div class="form-grid">
        <div class="form-field">
            <label for="chunk_file">Файл</label>
            <input type="file" id="chunk_file">
        </div>
        <div class="form-field">
            <label for="chunk_access">Доступ</label>
            <select id="chunk_access">
                <option value="public">Открытый (прямая ссылка)</option>
                <option value="protected">Защищённый (только по сессии или токену)</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="button" id="chunk_upload_btn" class="btn"
                data-csrf="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES) ?>">Загрузить по частям</button>
        </div>
        <div id="chunk_progress" class="form-hint"></div>
    </div>
</div>
<form method="get" action="/admin/files" class="list-filters list-filters--panel" style="margin-bottom: 20px;">
    <div class="list-filters__group">
        <input type="text" name="q" value="<?= htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES) ?>" placeholder="Поиск по имени…">
        
        <select name="type">
            <option value="">Все типы</option>
            <option value="image" <?= ($_GET['type'] ?? '') === 'image' ? 'selected' : '' ?>>Изображения</option>
            <option value="document" <?= ($_GET['type'] ?? '') === 'document' ? 'selected' : '' ?>>Документы</option>
            <option value="video" <?= ($_GET['type'] ?? '') === 'video' ? 'selected' : '' ?>>Видео</option>
        </select>

        <select name="sort">
            <option value="date_desc" <?= ($_GET['sort'] ?? '') === 'date_desc' ? 'selected' : '' ?>>Сначала новые</option>
            <option value="date_asc" <?= ($_GET['sort'] ?? '') === 'date_asc' ? 'selected' : '' ?>>Сначала старые</option>
            <option value="size_desc" <?= ($_GET['sort'] ?? '') === 'size_desc' ? 'selected' : '' ?>>Сначала крупные</option>
            <option value="size_asc" <?= ($_GET['sort'] ?? '') === 'size_asc' ? 'selected' : '' ?>>Сначала небольшие</option>
            <option value="name_asc" <?= ($_GET['sort'] ?? '') === 'name_asc' ? 'selected' : '' ?>>По имени (А-Я)</option>
            <option value="name_desc" <?= ($_GET['sort'] ?? '') === 'name_desc' ? 'selected' : '' ?>>По имени (Я-А)</option>
        </select>

        <button type="submit" class="btn">Применить</button>
        <?php if (!empty($_GET['q']) || !empty($_GET['type']) || !empty($_GET['sort'])): ?>
            <a href="/admin/files" class="btn" style="text-decoration:none; display:inline-flex; align-items:center; background:#e8e8e8; color:#333;">Сбросить</a>
        <?php endif; ?>
    </div>
</form>

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
                <td>
                    <div class="file-cell">
                        <?php if (str_starts_with((string) $item['mime_type'], 'image/')): ?>
                            <?php 
                            $thumbUrl = $item['access_type'] === 'public' 
                                ? FileEntry::publicUrl($item) 
                                : '/download.php?file_id=' . (int) $item['id'] . '&token=' . htmlspecialchars((string) $item['access_token'], ENT_QUOTES); 
                            ?>
                            <div class="file-thumbnail">
                                <img src="<?= htmlspecialchars($thumbUrl, ENT_QUOTES) ?>" alt="" loading="lazy">
                            </div>
                        <?php else: ?>
                            <div class="file-thumbnail file-thumbnail--icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                        <?php endif; ?>
                        <span class="file-name"><?= htmlspecialchars($item['original_name'], ENT_QUOTES) ?></span>
                    </div>
                </td>
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
