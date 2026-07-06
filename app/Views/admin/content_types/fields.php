<?php

use App\Core\Csrf;

$pageTitle = 'Поля типа: ' . ($type['name'] ?? '');
$activeNav = 'content_types';
require __DIR__ . '/../layout/header.php';

/** @var array $type */
/** @var array $fields */
/** @var array $allTypes */
$ftypes = ['text' => 'Текст', 'textarea' => 'Многострочный', 'number' => 'Число', 'date' => 'Дата', 'image' => 'Изображение', 'file' => 'Файл', 'relation' => 'Связь'];
?>
<a href="/admin/content-types" class="btn btn--small" style="margin-bottom:16px;">&larr; Все типы</a>
<div class="form-card">
    <form method="post" action="/admin/content-types/<?= (int) $type['id'] ?>/fields" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="name">Название типа</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars((string) $type['name'], ENT_QUOTES) ?>" required>
        </div>
        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="has_translations" name="has_translations" value="1" <?= (int) $type['has_translations'] === 1 ? 'checked' : '' ?>>
            <label for="has_translations">Мультиязычный</label>
        </div>

        <h3>Поля</h3>
        <div data-repeater="cfields">
            <?php foreach ($fields as $i => $f): ?>
                <div class="repeater-row">
                    <div class="form-field"><label>Имя (латиница)</label><input type="text" name="fields[<?= $i ?>][name]" value="<?= htmlspecialchars((string) $f['name'], ENT_QUOTES) ?>"></div>
                    <div class="form-field"><label>Подпись</label><input type="text" name="fields[<?= $i ?>][label]" value="<?= htmlspecialchars((string) $f['label'], ENT_QUOTES) ?>"></div>
                    <div class="form-field"><label>Тип</label>
                        <select name="fields[<?= $i ?>][field_type]">
                            <?php foreach ($ftypes as $v => $l): ?><option value="<?= $v ?>" <?= $f['field_type'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field"><label>Связь с типом (для «Связь»)</label>
                        <input type="text" name="fields[<?= $i ?>][relation_type]" placeholder="slug типа" value="<?= htmlspecialchars((string) ($f['options']['relation_type'] ?? ''), ENT_QUOTES) ?>">
                    </div>
                    <div class="form-field form-field--checkbox"><input type="checkbox" name="fields[<?= $i ?>][required]" value="1" <?= !empty($f['required']) ? 'checked' : '' ?>><label>Обязательное</label></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </div>
            <?php endforeach; ?>
        </div>
        <template data-repeater-template="cfields">
            <div class="form-field"><label>Имя (латиница)</label><input type="text" name="fields[__INDEX__][name]"></div>
            <div class="form-field"><label>Подпись</label><input type="text" name="fields[__INDEX__][label]"></div>
            <div class="form-field"><label>Тип</label>
                <select name="fields[__INDEX__][field_type]">
                    <?php foreach ($ftypes as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label>Связь с типом (для «Связь»)</label><input type="text" name="fields[__INDEX__][relation_type]" placeholder="slug типа"></div>
            <div class="form-field form-field--checkbox"><input type="checkbox" name="fields[__INDEX__][required]" value="1"><label>Обязательное</label></div>
            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
        </template>
        <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="cfields">+ Добавить поле</button></div>

        <div class="form-actions"><button type="submit" class="btn btn--primary">Сохранить поля</button></div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
