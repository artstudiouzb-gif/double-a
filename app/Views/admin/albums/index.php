<?php

use App\Core\Csrf;
use App\Models\Language;

$pageTitle = 'Фотоальбомы';
$activeNav = 'albums';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
$langs = Language::active();
$siteLangs = array_map(static fn (array $l): string => (string) $l['code'], $langs);
$langMap = \App\Models\PhotoAlbum::availableLangsForIds(array_map(static fn ($i): int => (int) $i['id'], $items));
?>
<p class="form-hint">Фотоальбомы выводятся на сайте по адресу <code>/albums</code>. Создайте альбом, затем наполните его фотографиями из медиабиблиотеки.</p>

<div class="form-card" style="margin-bottom:20px;">
    <h2 style="margin-top:0;">Новый альбом</h2>
    <form method="post" action="/admin/albums/create" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="title">Название</label>
            <input type="text" id="title" name="title" placeholder="Например: Итоги года — 2026" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Создать и перейти к фото</button>
        </div>
    </form>
</div>

<?php if (empty($items)): ?>
    <p class="form-hint">Альбомов пока нет.</p>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Название</th><th>Адрес</th><th>Языки</th><th>Фото</th><th>Статус</th><th>Создан</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></td>
                    <td><code style="font-size:12px;">/albums/<?= htmlspecialchars((string) $item['slug'], ENT_QUOTES) ?></code></td>
                    <td style="white-space:nowrap;"><?= \App\Core\View::renderPartial('admin/layout/lang_badges', ['siteLangs' => $siteLangs, 'has' => $langMap[(int) $item['id']] ?? []]) ?></td>
                    <td><?= (int) $item['images_count'] ?></td>
                    <td>
                        <?php if ((int) $item['is_published'] === 1): ?>
                            <span class="badge badge--success">Опубликован</span>
                        <?php else: ?>
                            <span class="badge">Черновик</span>
                        <?php endif; ?>
                        <?php if (!empty($item['is_featured'])): ?><span class="badge badge--success" title="Показывается в блоке «Медиа» на главной">★ на главной</span><?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;"><?= htmlspecialchars((string) $item['created_at'], ENT_QUOTES) ?></td>
                    <td class="data-table__actions" style="white-space:nowrap;">
                        <a href="/admin/albums/<?= (int) $item['id'] ?>/edit" class="btn btn--small">Редактировать</a>
                        <form method="post" action="/admin/albums/<?= (int) $item['id'] ?>/delete" style="display:inline;" data-confirm="Удалить альбом «<?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?>» вместе с составом?">
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
