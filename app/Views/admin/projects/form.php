<?php

use App\Core\Csrf;

$isEdit = !empty($project['id']);
$pageTitle = $isEdit ? 'Редактирование проекта' : 'Новый проект';
$activeNav = 'projects';
require __DIR__ . '/../layout/header.php';

/** @var array|null $project */
/** @var array $images */
/** @var array $fields */
/** @var string|null $error */

$action = $isEdit ? '/admin/projects/' . (int) $project['id'] . '/edit' : '/admin/projects/create';
?>
<div class="form-card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="form-grid">
        <?= Csrf::field() ?>

        <div class="form-field">
            <label for="title">Название проекта</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($project['title'] ?? '', ENT_QUOTES) ?>" required>
        </div>

        <div class="form-field">
            <label for="slug">ЧПУ (slug)</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($project['slug'] ?? '', ENT_QUOTES) ?>" placeholder="оставьте пустым для автогенерации">
        </div>

        <div class="form-field">
            <label for="description">Описание</label>
            <textarea id="description" name="description" style="min-height:160px;"><?= htmlspecialchars($project['description'] ?? '', ENT_QUOTES) ?></textarea>
        </div>

        <div class="form-field">
            <label for="cover_image_file">Обложка (файл)</label>
            <input type="file" id="cover_image_file" name="cover_image_file" accept="image/*">
        </div>
        <div class="form-field">
            <label for="cover_image_url">...либо ссылка на обложку</label>
            <input type="text" id="cover_image_url" name="cover_image_url" value="<?= htmlspecialchars($project['cover_image'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="status">Статус</label>
            <select id="status" name="status">
                <option value="draft" <?= ($project['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                <option value="published" <?= ($project['status'] ?? '') === 'published' ? 'selected' : '' ?>>Опубликовано</option>
            </select>
        </div>

        <div class="form-field">
            <label for="sort_order">Порядок сортировки</label>
            <input type="number" id="sort_order" name="sort_order" value="<?= (int) ($project['sort_order'] ?? 0) ?>">
        </div>

        <div>
            <label>Галерея изображений</label>
            <div data-repeater="gallery">
                <?php foreach ($images as $i => $image): ?>
                    <div class="repeater-row">
                        <div class="form-field">
                            <label>Ссылка на изображение</label>
                            <input type="text" name="gallery[<?= $i ?>][file_path]" value="<?= htmlspecialchars($image['file_path'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/....jpg">
                        </div>
                        <div class="form-field">
                            <label>Подпись</label>
                            <input type="text" name="gallery[<?= $i ?>][caption]" value="<?= htmlspecialchars($image['caption'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-repeater-template="gallery">
                <div class="form-field">
                    <label>Ссылка на изображение</label>
                    <input type="text" name="gallery[__INDEX__][file_path]" placeholder="/uploads/public/....jpg">
                </div>
                <div class="form-field">
                    <label>Подпись</label>
                    <input type="text" name="gallery[__INDEX__][caption]">
                </div>
                <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
            </template>
            <div class="repeater-actions">
                <button type="button" class="btn btn--small" data-repeater-add="gallery">+ Добавить изображение</button>
            </div>
            <span class="form-hint">Сначала загрузите изображения в разделе «Файлы» (публичный доступ), затем вставьте сюда ссылки.</span>
        </div>

        <div>
            <label>Кастомные поля (заказчик, год, площадь и т.п.)</label>
            <div data-repeater="custom_fields">
                <?php foreach ($fields as $i => $field): ?>
                    <div class="repeater-row">
                        <div class="form-field">
                            <label>Название поля</label>
                            <input type="text" name="custom_fields[<?= $i ?>][key]" value="<?= htmlspecialchars($field['field_key'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>Значение</label>
                            <input type="text" name="custom_fields[<?= $i ?>][value]" value="<?= htmlspecialchars($field['field_value'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-repeater-template="custom_fields">
                <div class="form-field">
                    <label>Название поля</label>
                    <input type="text" name="custom_fields[__INDEX__][key]">
                </div>
                <div class="form-field">
                    <label>Значение</label>
                    <input type="text" name="custom_fields[__INDEX__][value]">
                </div>
                <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
            </template>
            <div class="repeater-actions">
                <button type="button" class="btn btn--small" data-repeater-add="custom_fields">+ Добавить поле</button>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить</button>
            <a href="/admin/projects" class="btn">Отмена</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
