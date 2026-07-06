<?php

use App\Core\Csrf;

$pageTitle = 'Соцсети — авто-публикация';
$activeNav = 'social';
require __DIR__ . '/../layout/header.php';

/** @var array $config */
$labels = [
    'facebook' => 'Facebook (страница)',
    'linkedin' => 'LinkedIn (организация/профиль)',
    'instagram' => 'Instagram (Business-аккаунт)',
];
$fieldLabels = [
    'token' => 'Access Token',
    'page_id' => 'ID страницы',
    'author' => 'Author URN (напр. urn:li:organization:123)',
    'user_id' => 'IG User ID',
];
?>
<div class="form-card">
    <p class="form-hint">
        При публикации новости она автоматически ставится в очередь и
        отправляется в включённые сети CLI-воркером
        (<code>app/Console/social_worker.php</code> по Cron). Токены получаются
        в кабинетах разработчика соответствующих платформ. Instagram требует
        публичную обложку новости.
    </p>
    <form method="post" action="/admin/social" class="form-grid">
        <?= Csrf::field() ?>
        <?php foreach ($config as $net => $c): ?>
            <fieldset style="border:1px solid var(--admin-border);border-radius:8px;padding:16px;margin-bottom:8px;">
                <legend style="padding:0 8px;font-weight:600;">
                    <?= htmlspecialchars($labels[$net] ?? $net, ENT_QUOTES) ?>
                    <?php if ($c['enabled'] && !$c['ready']): ?>
                        <span class="badge badge--draft">не заполнено</span>
                    <?php elseif ($c['ready']): ?>
                        <span class="badge badge--published">готово</span>
                    <?php endif; ?>
                </legend>
                <div class="form-field form-field--checkbox">
                    <input type="checkbox" id="<?= $net ?>_enabled" name="<?= $net ?>[enabled]" value="1" <?= $c['enabled'] ? 'checked' : '' ?>>
                    <label for="<?= $net ?>_enabled">Публиковать новости в <?= htmlspecialchars($labels[$net] ?? $net, ENT_QUOTES) ?></label>
                </div>
                <?php foreach ($c['fields'] as $field => $value): ?>
                    <div class="form-field">
                        <label for="<?= $net ?>_<?= $field ?>"><?= htmlspecialchars($fieldLabels[$field] ?? $field, ENT_QUOTES) ?></label>
                        <input type="<?= $field === 'token' ? 'password' : 'text' ?>" id="<?= $net ?>_<?= $field ?>"
                               name="<?= $net ?>[<?= $field ?>]" value="<?= htmlspecialchars((string) $value, ENT_QUOTES) ?>"
                               autocomplete="off">
                    </div>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
