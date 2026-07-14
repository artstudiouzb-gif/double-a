<?php

use App\Core\AdminUi;
use App\Core\Csrf;

/** @var array $video */

$pageTitle = 'Видео: ' . $video['title'];
$activeNav = 'videos';
require __DIR__ . '/../layout/header.php';
?>
<p><a href="/admin/videos" class="btn btn--small">← Все видео</a></p>

<div class="form-card">
    <h2 style="margin-top:0;">Свойства видео</h2>
    <form method="post" action="/admin/videos/<?= (int) $video['id'] ?>/update" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="title">Название</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars((string) $video['title'], ENT_QUOTES) ?>" required>
        </div>
        <div class="form-field">
            <label for="description">Описание</label>
            <textarea id="description" name="description" rows="3"><?= htmlspecialchars((string) ($video['description'] ?? ''), ENT_QUOTES) ?></textarea>
        </div>
        <?= AdminUi::imageField('cover_url', (string) ($video['cover_url'] ?? ''), [
            'label' => 'Обложка',
            'file' => 'cover_file',
            'hint' => 'Кадр-превью видео. Показывается в блоке «Медиа» на главной.',
        ]) ?>
        <div class="form-field">
            <label for="video_url">Ссылка на видео</label>
            <input type="text" id="video_url" name="video_url" value="<?= htmlspecialchars((string) ($video['video_url'] ?? ''), ENT_QUOTES) ?>" placeholder="https://youtube.com/watch?v=…">
            <span class="form-hint">YouTube или прямая ссылка. По ней открывается видео из карточки.</span>
        </div>
        <div class="form-field">
            <label for="duration">Длительность</label>
            <input type="text" id="duration" name="duration" value="<?= htmlspecialchars((string) ($video['duration'] ?? ''), ENT_QUOTES) ?>" placeholder="напр. 02:35">
        </div>
        <div class="form-field">
            <label for="sort_order">Порядок сортировки</label>
            <input type="number" id="sort_order" name="sort_order" value="<?= (int) ($video['sort_order'] ?? 0) ?>">
        </div>
        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="is_published" name="is_published" value="1" <?= (int) $video['is_published'] === 1 ? 'checked' : '' ?>>
            <label for="is_published">Опубликовано (видно на сайте)</label>
        </div>
        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= (int) ($video['is_featured'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label for="is_featured">Показать на главной (блок «Медиа»)</label>
        </div>
        <div class="form-actions form-actions--sticky">
            <button type="submit" class="btn btn--primary">Сохранить</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
