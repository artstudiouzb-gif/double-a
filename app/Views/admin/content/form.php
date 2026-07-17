<?php

use App\Core\ContentFields;
use App\Core\Csrf;
use App\Models\Language;

/** @var array $type */
/** @var array $fields */
/** @var array|null $entry */
/** @var array $translations */
/** @var string|null $error */

$isEdit = $entry !== null;
$pageTitle = ($isEdit ? 'Редактирование' : 'Новая запись') . ': ' . $type['name'];
$activeNav = 'content:' . $type['slug'];
require __DIR__ . '/../layout/header.php';

$data = $entry['data'] ?? [];
$action = $isEdit
    ? '/admin/content/' . $type['slug'] . '/' . (int) $entry['id'] . '/edit'
    : '/admin/content/' . $type['slug'] . '/create';
$hasFile = false;
foreach ($fields as $f) { if ($f['field_type'] === 'file') { $hasFile = true; break; } }
$hasTr = (int) $type['has_translations'] === 1;
?>
<a href="/admin/content/<?= htmlspecialchars((string) $type['slug'], ENT_QUOTES) ?>" class="btn btn--small" style="margin-bottom:16px;">&larr; Все записи</a>
<div class="form-card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="<?= $action ?>" class="form-grid"<?= $hasFile ? ' enctype="multipart/form-data"' : '' ?>>
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="title">Заголовок</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars((string) ($entry['title'] ?? ''), ENT_QUOTES) ?>" required>
        </div>
        <div class="form-field">
            <label for="slug">Адрес (slug, необязательно)</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars((string) ($entry['slug'] ?? ''), ENT_QUOTES) ?>">
        </div>

        <?php foreach ($fields as $field): ?>
            <?= ContentFields::renderInput($field, $data[$field['name']] ?? '', 'f_') ?>
        <?php endforeach; ?>

        <?php if ($hasTr): ?>
            <hr style="border:none;border-top:1px solid var(--admin-border);">
            <h3>Переводы</h3>
            <?php foreach (Language::active() as $lang): ?>
                <?php $code = (string) $lang['code']; if ($code === Language::defaultCode()) { continue; } ?>
                <?php $tr = $translations[$code] ?? ['title' => '', 'data' => []]; ?>
                <fieldset style="border:1px solid var(--admin-border);border-radius:8px;padding:14px;margin-bottom:10px;">
                    <legend><?= htmlspecialchars((string) $lang['name'], ENT_QUOTES) ?></legend>
                    <div class="form-field">
                        <label>Заголовок (<?= htmlspecialchars($code, ENT_QUOTES) ?>)</label>
                        <input type="text" name="title_<?= $code ?>" value="<?= htmlspecialchars((string) ($tr['title'] ?? ''), ENT_QUOTES) ?>">
                    </div>
                    <?php foreach ($fields as $field): ?>
                        <?php if ($field['field_type'] === 'file') { continue; /* файлы не переводим */ } ?>
                        <?= ContentFields::renderInput($field, $tr['data'][$field['name']] ?? '', 't_' . $code . '_') ?>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="form-field">
            <label for="status">Статус</label>
            <select id="status" name="status">
                <option value="draft" <?= ($entry['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                <option value="published" <?= ($entry['status'] ?? '') === 'published' ? 'selected' : '' ?>>Опубликовано</option>
            </select>
        </div>
        <div class="form-actions form-actions--sticky"><button type="submit" class="btn btn--primary"><?= \App\Core\AdminUi::icon('save') ?>Сохранить</button></div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
