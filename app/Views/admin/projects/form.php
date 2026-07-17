<?php

use App\Core\Csrf;
use App\Models\Language;

$isEdit = !empty($project['id']);
$pageTitle = $isEdit ? 'Редактирование проекта' : 'Новый проект';
$activeNav = 'projects';
require __DIR__ . '/../layout/header.php';

/** @var array|null $project */
/** @var array $images */
/** @var array $fields */
/** @var array $translations */
/** @var string|null $error */

$defaultCode = Language::defaultCode();
$translationLangs = array_values(array_filter(
    Language::active(),
    static fn (array $l): bool => (string) $l['code'] !== $defaultCode
));

$action = $isEdit ? '/admin/projects/' . (int) $project['id'] . '/edit' : '/admin/projects/create';
?>
<div class="form-card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <?php if ($isEdit): ?>
        <div style="margin-bottom:16px;"><a class="btn btn--small" href="/admin/revisions/project/<?= (int) $project['id'] ?>">История версий</a></div>
    <?php endif; ?>
    <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="form-grid" data-content-draft="project:<?= $isEdit ? (int) $project['id'] : 'new' ?>" data-record-updated="<?= htmlspecialchars((string) ($project['updated_at'] ?? ''), ENT_QUOTES) ?>">
        <?= Csrf::field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="expected_updated_at" value="<?= htmlspecialchars((string) $project['updated_at'], ENT_QUOTES) ?>">
            <input type="hidden" name="expected_lock_version" value="<?= (int) ($project['lock_version'] ?? 1) ?>">
        <?php endif; ?>

        <div class="form-field">
            <label for="title">Название проекта</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($project['title'] ?? '', ENT_QUOTES) ?>" required>
        </div>

        <div class="form-field">
            <label for="slug">ЧПУ (slug)</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($project['slug'] ?? '', ENT_QUOTES) ?>" placeholder="оставьте пустым для автогенерации">
        </div>

        <div class="form-field">
            <label for="description">Описание<?php if ($translationLangs): ?> <span class="form-hint" style="font-weight:400;">(основной язык)</span><?php endif; ?></label>
            <textarea id="description" name="description" style="min-height:160px;"><?= htmlspecialchars($project['description'] ?? '', ENT_QUOTES) ?></textarea>
        </div>

        <?php if ($translationLangs): ?>
            <div data-lang-tabs style="border:1px solid var(--admin-border,#e3e6ea);border-radius:8px;padding:12px;">
                <div class="lang-tabs">
                    <?php foreach ($translationLangs as $i => $lang): ?>
                        <button type="button" class="lang-tab-btn <?= $i === 0 ? 'is-active' : '' ?>" data-lang-target="<?= htmlspecialchars($lang['code'], ENT_QUOTES) ?>">
                            <?= htmlspecialchars($lang['name'], ENT_QUOTES) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($translationLangs as $i => $lang): ?>
                    <?php $code = (string) $lang['code']; $t = $translations[$code] ?? []; ?>
                    <div class="lang-tab-panel <?= $i === 0 ? 'is-active' : '' ?>" data-lang-panel="<?= htmlspecialchars($code, ENT_QUOTES) ?>">
                        <p class="form-hint">Перевод для языка «<?= htmlspecialchars($lang['name'], ENT_QUOTES) ?>». Пустые поля на сайте заменяются версией основного языка.</p>
                        <div class="form-field">
                            <label>Название проекта</label>
                            <input type="text" name="translations[<?= $code ?>][title]" value="<?= htmlspecialchars($t['title'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>Описание</label>
                            <textarea name="translations[<?= $code ?>][description]" style="min-height:160px;"><?= htmlspecialchars($t['description'] ?? '', ENT_QUOTES) ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= \App\Core\AdminUi::imageField('cover_image_url', $project['cover_image'] ?? '', [
            'label' => 'Обложка проекта',
            'file' => 'cover_image_file',
        ]) ?>

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

        <div class="form-field">
            <label class="checkbox-label">
                <input type="checkbox" name="is_featured" value="1" <?= !empty($project['is_featured']) ? 'checked' : '' ?>>
                Показать на главной
            </label>
            <span class="form-hint">Блок «Проекты» на главной может автоматически собирать отмеченные проекты — не нужно дублировать их вручную.</span>
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
                        <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove><?= \App\Core\AdminUi::icon('trash') ?>Удалить</button>
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
                <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove><?= \App\Core\AdminUi::icon('trash') ?>Удалить</button>
            </template>
            <div class="repeater-actions">
                <button type="button" class="btn btn--small" data-repeater-add="gallery"><?= \App\Core\AdminUi::icon('plus') ?>Добавить изображение</button>
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
                        <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove><?= \App\Core\AdminUi::icon('trash') ?>Удалить</button>
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
                <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove><?= \App\Core\AdminUi::icon('trash') ?>Удалить</button>
            </template>
            <div class="repeater-actions">
                <button type="button" class="btn btn--small" data-repeater-add="custom_fields"><?= \App\Core\AdminUi::icon('plus') ?>Добавить поле</button>
            </div>
        </div>

        <div class="form-actions form-actions--sticky">
            <button type="submit" class="btn btn--primary"><?= \App\Core\AdminUi::icon('save') ?>Сохранить</button>
            <a href="/admin/projects" class="btn">Отмена</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
