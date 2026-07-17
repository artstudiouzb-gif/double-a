<?php

use App\Core\Csrf;
use App\Models\Language;

$isEdit = !empty($member['id']);
$pageTitle = $isEdit ? 'Редактирование сотрудника' : 'Новый сотрудник';
$activeNav = 'team';
require __DIR__ . '/../layout/header.php';

/** @var array|null $member */
/** @var array $translations */
/** @var string|null $error */

$action = $isEdit ? '/admin/team/' . (int) $member['id'] . '/edit' : '/admin/team/create';
$socials = $member['socials'] ?? [];
$defaultCode = Language::defaultCode();
$translationLangs = array_values(array_filter(
    Language::active(),
    static fn (array $l): bool => (string) $l['code'] !== $defaultCode
));
?>
<div class="form-card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="form-grid">
        <?= Csrf::field() ?>

        <div class="form-field">
            <label for="name">Имя</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($member['name'] ?? '', ENT_QUOTES) ?>" required>
        </div>

        <div class="form-field">
            <label for="position">Должность<?php if ($translationLangs): ?> <span class="form-hint" style="font-weight:400;">(основной язык)</span><?php endif; ?></label>
            <input type="text" id="position" name="position" value="<?= htmlspecialchars($member['position'] ?? '', ENT_QUOTES) ?>">
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
                            <label>Имя</label>
                            <input type="text" name="translations[<?= $code ?>][name]" value="<?= htmlspecialchars($t['name'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>Должность</label>
                            <input type="text" name="translations[<?= $code ?>][position]" value="<?= htmlspecialchars($t['position'] ?? '', ENT_QUOTES) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= \App\Core\AdminUi::imageField('photo_url', $member['photo'] ?? '', [
            'label' => 'Фото сотрудника',
            'file' => 'photo_file',
        ]) ?>

        <div class="form-field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($member['email'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="phone">Телефон</label>
            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($member['phone'] ?? '', ENT_QUOTES) ?>">
        </div>

        <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'telegram' => 'Telegram', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp'] as $key => $label): ?>
            <div class="form-field">
                <label for="social_<?= $key ?>"><?= $label ?></label>
                <input type="text" id="social_<?= $key ?>" name="social_<?= $key ?>" value="<?= htmlspecialchars($socials[$key] ?? '', ENT_QUOTES) ?>">
            </div>
        <?php endforeach; ?>

        <div class="form-field">
            <label for="status">Статус</label>
            <select id="status" name="status">
                <option value="published" <?= ($member['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Опубликовано</option>
                <option value="draft" <?= ($member['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Черновик</option>
            </select>
        </div>

        <div class="form-field">
            <label for="sort_order">Порядок сортировки</label>
            <input type="number" id="sort_order" name="sort_order" value="<?= (int) ($member['sort_order'] ?? 0) ?>">
        </div>

        <div class="form-actions form-actions--sticky">
            <button type="submit" class="btn btn--primary">Сохранить</button>
            <a href="/admin/team" class="btn">Отмена</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
