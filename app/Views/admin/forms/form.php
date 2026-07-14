<?php

use App\Core\Csrf;

$isEdit = !empty($form['id']);
$pageTitle = $isEdit ? 'Редактирование формы' : 'Новая форма';
$activeNav = 'forms';
require __DIR__ . '/../layout/header.php';

/** @var array|null $form */
/** @var string|null $error */

$action = $isEdit ? '/admin/forms/' . (int) $form['id'] . '/edit' : '/admin/forms/create';
$fields = $form['fields'] ?? [];
?>
<div class="form-card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="<?= $action ?>" class="form-grid">
        <?= Csrf::field() ?>

        <div class="form-field">
            <label for="name">Название формы</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($form['name'] ?? '', ENT_QUOTES) ?>" required>
        </div>

        <div class="form-field">
            <label for="slug">Slug (используется в адресе отправки)</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($form['slug'] ?? '', ENT_QUOTES) ?>" placeholder="оставьте пустым для автогенерации">
        </div>

        <div class="form-field">
            <label for="notify_email">Email для уведомлений о новых заявках</label>
            <input type="email" id="notify_email" name="notify_email" value="<?= htmlspecialchars($form['notify_email'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="success_message">Сообщение после успешной отправки</label>
            <input type="text" id="success_message" name="success_message" value="<?= htmlspecialchars($form['success_message'] ?? 'Спасибо! Ваша заявка отправлена.', ENT_QUOTES) ?>">
        </div>

        <div>
            <label>Поля формы</label>
            <div data-repeater="fields">
                <?php foreach ($fields as $i => $field): ?>
                    <div class="repeater-row">
                        <div class="form-field">
                            <label>Имя поля (латиница, для БД)</label>
                            <input type="text" name="fields[<?= $i ?>][name]" value="<?= htmlspecialchars($field['name'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>Подпись поля</label>
                            <input type="text" name="fields[<?= $i ?>][label]" value="<?= htmlspecialchars($field['label'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>Тип поля</label>
                            <select name="fields[<?= $i ?>][type]">
                                <?php foreach (['text' => 'Текст', 'email' => 'Email', 'tel' => 'Телефон', 'textarea' => 'Многострочный текст', 'file' => 'Файл'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= ($field['type'] ?? 'text') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field form-field--checkbox">
                            <input type="checkbox" name="fields[<?= $i ?>][required]" value="1" <?= !empty($field['required']) ? 'checked' : '' ?>>
                            <label>Обязательное поле</label>
                        </div>
                        <div class="form-field">
                            <label>Условие показа (необязательно)</label>
                            <div style="display:flex;gap:8px;">
                                <input type="text" name="fields[<?= $i ?>][condition_field]" placeholder="имя другого поля" value="<?= htmlspecialchars($field['condition']['field'] ?? '', ENT_QUOTES) ?>">
                                <input type="text" name="fields[<?= $i ?>][condition_value]" placeholder="= значение" value="<?= htmlspecialchars($field['condition']['value'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <span class="form-hint">Поле показывается только если указанное поле равно значению.</span>
                        </div>
                        <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить поле</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-repeater-template="fields">
                <div class="form-field">
                    <label>Имя поля (латиница, для БД)</label>
                    <input type="text" name="fields[__INDEX__][name]">
                </div>
                <div class="form-field">
                    <label>Подпись поля</label>
                    <input type="text" name="fields[__INDEX__][label]">
                </div>
                <div class="form-field">
                    <label>Тип поля</label>
                    <select name="fields[__INDEX__][type]">
                        <option value="text">Текст</option>
                        <option value="email">Email</option>
                        <option value="tel">Телефон</option>
                        <option value="textarea">Многострочный текст</option>
                        <option value="file">Файл</option>
                    </select>
                </div>
                <div class="form-field form-field--checkbox">
                    <input type="checkbox" name="fields[__INDEX__][required]" value="1">
                    <label>Обязательное поле</label>
                </div>
                <div class="form-field">
                    <label>Условие показа (необязательно)</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="fields[__INDEX__][condition_field]" placeholder="имя другого поля">
                        <input type="text" name="fields[__INDEX__][condition_value]" placeholder="= значение">
                    </div>
                </div>
                <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить поле</button>
            </template>
            <div class="repeater-actions">
                <button type="button" class="btn btn--small" data-repeater-add="fields">+ Добавить поле</button>
            </div>
        </div>

        <div class="form-actions form-actions--sticky">
            <button type="submit" class="btn btn--primary">Сохранить</button>
            <a href="/admin/forms" class="btn">Отмена</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
