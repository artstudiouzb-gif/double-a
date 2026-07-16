<?php

use App\Core\Csrf;

/** @var string|null $error */
/** @var array $languages */
/** @var array $timezones */
$step = '3';
require __DIR__ . '/_header.php';
?>
<p class="auth-hint">Базовые параметры сайта.</p>
<?php if (!empty($error)): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
<form method="post" action="/install/step3" class="form-grid">
    <?= Csrf::field() ?>
    <div class="form-field">
        <label for="site_name">Название сайта</label>
        <input type="text" id="site_name" name="site_name" value="ArtStudio" required>
    </div>
    <div class="form-field">
        <label for="site_description">Описание (для SEO)</label>
        <input type="text" id="site_description" name="site_description" value="">
    </div>
    <div class="form-field">
        <label for="timezone">Часовой пояс</label>
        <select id="timezone" name="timezone">
            <?php foreach ($timezones as $tz): ?>
                <option value="<?= htmlspecialchars($tz, ENT_QUOTES) ?>" <?= $tz === 'Asia/Tashkent' ? 'selected' : '' ?>><?= htmlspecialchars($tz, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-field">
        <label for="default_language">Язык по умолчанию</label>
        <select id="default_language" name="default_language">
            <?php foreach ($languages as $lang): ?>
                <option value="<?= htmlspecialchars($lang['code'], ENT_QUOTES) ?>" <?= $lang['is_default'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lang['name'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn--primary">Продолжить →</button>
    </div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
