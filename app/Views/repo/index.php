<?php

use App\Core\Format;

/** @var array $files */
/** @var list<string> $categories */
/** @var string $query */
/** @var string $category */
/** @var array|null $repoUser */
$pageTitle = 'Файлы';
require __DIR__ . '/layout/top.php';
?>
<h1 class="repo-page-title">Файлы</h1>

<div class="repo-card">
    <form method="get" action="/repo" class="repo-toolbar">
        <div class="repo-field">
            <label for="q">Поиск</label>
            <input type="text" id="q" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>" placeholder="Название, описание или имя файла">
        </div>
        <div class="repo-field" style="flex:0 0 220px;">
            <label for="category">Категория</label>
            <select id="category" name="category">
                <option value="">Все категории</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat, ENT_QUOTES) ?>" <?= $cat === $category ? 'selected' : '' ?>><?= htmlspecialchars($cat, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="repo-field" style="flex:0 0 auto;">
            <button type="submit" class="repo-btn">Найти</button>
            <?php if ($query !== '' || $category !== ''): ?>
                <a href="/repo" class="repo-btn repo-btn--ghost">Сброс</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="repo-card">
    <?php if (empty($files)): ?>
        <div class="repo-empty">
            <?= ($query !== '' || $category !== '') ? 'По вашему запросу ничего не найдено.' : 'В хранилище пока нет файлов.' ?>
        </div>
    <?php else: ?>
        <table class="repo-table">
            <thead>
                <tr>
                    <th>Файл</th>
                    <th>Категория</th>
                    <th>Размер</th>
                    <th>Добавлен</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $f): ?>
                    <tr>
                        <td>
                            <div class="repo-file-title"><?= htmlspecialchars((string) $f['title'], ENT_QUOTES) ?></div>
                            <?php if (!empty($f['description'])): ?>
                                <div class="repo-file-desc"><?= htmlspecialchars((string) $f['description'], ENT_QUOTES) ?></div>
                            <?php endif; ?>
                            <div class="repo-meta"><?= htmlspecialchars((string) $f['original_name'], ENT_QUOTES) ?></div>
                        </td>
                        <td>
                            <?php if (!empty($f['category'])): ?>
                                <span class="repo-badge"><?= htmlspecialchars((string) $f['category'], ENT_QUOTES) ?></span>
                            <?php else: ?>
                                <span class="repo-meta">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="repo-meta"><?= htmlspecialchars(Format::fileSize((int) $f['size']), ENT_QUOTES) ?></td>
                        <td class="repo-meta"><?= htmlspecialchars(date('d.m.Y', strtotime((string) $f['created_at'])), ENT_QUOTES) ?></td>
                        <td style="text-align:right;">
                            <a href="/repo/download/<?= (int) $f['id'] ?>" class="repo-btn repo-btn--sm">Скачать</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/layout/bottom.php'; ?>
