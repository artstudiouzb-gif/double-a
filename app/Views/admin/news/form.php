<?php

use App\Core\Csrf;

$isEdit = !empty($news['id']);
$pageTitle = $isEdit ? 'Редактирование новости' : 'Новая новость';
$activeNav = 'news';
require __DIR__ . '/../layout/header.php';

/** @var array|null $news */
/** @var string|null $error */

$action = $isEdit ? '/admin/news/' . (int) $news['id'] . '/edit' : '/admin/news/create';
$publishedAtValue = '';
if (!empty($news['published_at'])) {
    $publishedAtValue = str_replace(' ', 'T', substr((string) $news['published_at'], 0, 16));
}
?>
<div class="form-card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="form-grid">
        <?= Csrf::field() ?>

        <div class="form-field">
            <label for="title">Заголовок</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($news['title'] ?? '', ENT_QUOTES) ?>" required>
        </div>

        <div class="form-field">
            <label for="slug">ЧПУ (slug)</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($news['slug'] ?? '', ENT_QUOTES) ?>" placeholder="оставьте пустым для автогенерации">
            <span class="form-hint">Итоговый адрес: /news/&lt;slug&gt;</span>
        </div>

        <div class="form-field">
            <label for="excerpt">Краткое описание</label>
            <textarea id="excerpt" name="excerpt"><?= htmlspecialchars($news['excerpt'] ?? '', ENT_QUOTES) ?></textarea>
        </div>

        <div class="form-field">
            <label for="content">Текст новости (допускается HTML)</label>
            <textarea id="content" name="content" style="min-height:220px;"><?= htmlspecialchars($news['content'] ?? '', ENT_QUOTES) ?></textarea>
        </div>

        <div class="form-field">
            <label for="image_file">Изображение (файл)</label>
            <input type="file" id="image_file" name="image_file" accept="image/*">
        </div>
        <div class="form-field">
            <label for="image_url">...либо ссылка на изображение</label>
            <input type="text" id="image_url" name="image_url" value="<?= htmlspecialchars($news['image'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="status">Статус</label>
            <select id="status" name="status">
                <option value="draft" <?= ($news['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                <option value="published" <?= ($news['status'] ?? '') === 'published' ? 'selected' : '' ?>>Опубликовано</option>
            </select>
        </div>

        <div class="form-field">
            <label for="published_at">Дата публикации</label>
            <input type="datetime-local" id="published_at" name="published_at" value="<?= htmlspecialchars($publishedAtValue, ENT_QUOTES) ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить</button>
            <a href="/admin/news" class="btn">Отмена</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
