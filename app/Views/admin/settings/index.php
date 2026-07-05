<?php

use App\Core\Csrf;

$pageTitle = 'Настройки дизайна';
$activeNav = 'settings';
require __DIR__ . '/../layout/header.php';

/** @var array $settings */
?>
<div class="form-card">
    <form method="post" action="/admin/settings" enctype="multipart/form-data" class="form-grid">
        <?= Csrf::field() ?>

        <div class="form-field">
            <label for="site_name">Название сайта</label>
            <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="logo_file">Логотип (файл)</label>
            <input type="file" id="logo_file" name="logo_file" accept="image/*">
        </div>
        <div class="form-field">
            <label for="logo_url">...либо ссылка на логотип</label>
            <input type="text" id="logo_url" name="logo_url" value="<?= htmlspecialchars($settings['logo_url'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="color_primary">Основной цвет</label>
            <input type="text" id="color_primary" name="color_primary" value="<?= htmlspecialchars($settings['color_primary'] ?? '#1a1a1a', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="color_accent">Акцентный цвет</label>
            <input type="text" id="color_accent" name="color_accent" value="<?= htmlspecialchars($settings['color_accent'] ?? '#e63946', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="font_family">Шрифт (CSS font-family)</label>
            <input type="text" id="font_family" name="font_family" value="<?= htmlspecialchars($settings['font_family'] ?? "'Inter', sans-serif", ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="contact_phone">Телефон</label>
            <input type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($settings['contact_phone'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="contact_email">Email</label>
            <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="contact_address">Адрес</label>
            <input type="text" id="contact_address" name="contact_address" value="<?= htmlspecialchars($settings['contact_address'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="counter_codes">Коды счётчиков (Google Analytics, Яндекс.Метрика и т.п.)</label>
            <textarea id="counter_codes" name="counter_codes" style="min-height:140px; font-family: monospace;"><?= htmlspecialchars($settings['counter_codes'] ?? '', ENT_QUOTES) ?></textarea>
            <span class="form-hint">Вставляется в конец страницы как есть (доступно только администраторам).</span>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить настройки</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
