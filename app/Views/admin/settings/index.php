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

        <?= \App\Core\AdminUi::imageField('logo_url', $settings['logo_url'] ?? '', [
            'label' => 'Логотип',
            'file' => 'logo_file',
        ]) ?>

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
            <label for="default_theme">Тема оформления</label>
            <select id="default_theme" name="default_theme">
                <?php $th = $settings['default_theme'] ?? 'light'; ?>
                <option value="light" <?= $th === 'light' ? 'selected' : '' ?>>Светлая</option>
                <option value="dark" <?= $th === 'dark' ? 'selected' : '' ?>>Тёмная</option>
                <option value="auto" <?= $th === 'auto' ? 'selected' : '' ?>>Авто (по системе)</option>
            </select>
            <span class="form-hint">Посетители могут переключать тему; выбор сохраняется в браузере.</span>
        </div>

        <div class="form-field">
            <label for="font_face_name">Локальный шрифт: имя семейства</label>
            <input type="text" id="font_face_name" name="font_face_name" value="<?= htmlspecialchars($settings['font_face_name'] ?? '', ENT_QUOTES) ?>" placeholder="напр. MyBrandFont">
            <span class="form-hint">Если задать имя и ссылку на .woff2, шрифт подключится через @font-face с preload (без мерцания). Не забудьте указать это имя в поле «Шрифт» выше.</span>
        </div>
        <div class="form-field">
            <label for="font_url">Локальный шрифт: ссылка на .woff2</label>
            <input type="text" id="font_url" name="font_url" value="<?= htmlspecialchars($settings['font_url'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/font.woff2">
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

        <fieldset class="settings-group">
            <legend>Вход в панель: код через Telegram</legend>
            <div class="form-field">
                <label for="telegram_bot_token">Токен Telegram-бота (бесплатно, рекомендуется)</label>
                <input type="text" id="telegram_bot_token" name="telegram_bot_token" value="<?= htmlspecialchars($settings['telegram_bot_token'] ?? '', ENT_QUOTES) ?>" autocomplete="off" spellcheck="false">
                <span class="form-hint">Создайте бота у <strong>@BotFather</strong> в Telegram (команда <code>/newbot</code>) и вставьте токен. Каждый администратор затем привязывает свой Telegram в «Профиле» — коды входа приходят от бота бесплатно.</span>
            </div>
            <div class="form-field">
                <label for="telegram_gateway_token">Токен Telegram Gateway API (платный, резервный)</label>
                <input type="text" id="telegram_gateway_token" name="telegram_gateway_token" value="<?= htmlspecialchars($settings['telegram_gateway_token'] ?? '', ENT_QUOTES) ?>" autocomplete="off" spellcheck="false">
                <span class="form-hint">Получите токен в кабинете <code>gateway.telegram.org</code>. Когда токен указан и у администратора заполнен телефон, вход подтверждается 6-значным кодом, который приходит в Telegram от официального канала <strong>Verification&nbsp;Codes</strong> (t.me/VerificationCodes). Если поле пустое — вход только по паролю.</span>
            </div>
        </fieldset>

        <fieldset class="settings-group">
            <legend>Веб-аналитика и трекинг</legend>
            <div class="form-field">
                <label for="analytics_ga_id">Google Analytics ID</label>
                <input type="text" id="analytics_ga_id" name="analytics_ga_id" value="<?= htmlspecialchars($settings['analytics_ga_id'] ?? '', ENT_QUOTES) ?>" placeholder="G-XXXXXXXXXX">
                <span class="form-hint">Только идентификатор формата <code>G-XXXXXXXXXX</code>. Скрипт собирается автоматически (сырой JS не принимается).</span>
            </div>
            <div class="form-field">
                <label for="analytics_ym_id">Яндекс.Метрика ID</label>
                <input type="text" id="analytics_ym_id" name="analytics_ym_id" value="<?= htmlspecialchars($settings['analytics_ym_id'] ?? '', ENT_QUOTES) ?>" placeholder="12345678" inputmode="numeric">
            </div>
        </fieldset>

        <fieldset class="settings-group">
            <legend>Приватность и GDPR</legend>
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="cookie_consent_enabled" name="cookie_consent_enabled" value="1" <?= ($settings['cookie_consent_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                <label for="cookie_consent_enabled">Показывать Cookie-Consent баннер (счётчики грузятся только после согласия)</label>
            </div>
            <div class="form-field">
                <label for="privacy_policy_page_id">Страница «Политика конфиденциальности»</label>
                <select id="privacy_policy_page_id" name="privacy_policy_page_id">
                    <option value="">— не выбрано —</option>
                    <?php foreach (($pages ?? []) as $p): ?>
                        <?php if (($p['status'] ?? '') !== 'published') { continue; } ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) ($settings['privacy_policy_page_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $p['title'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="pii_retention_days">Срок хранения ПДн (дней, 0 — бессрочно)</label>
                <input type="number" id="pii_retention_days" name="pii_retention_days" min="0" value="<?= htmlspecialchars($settings['pii_retention_days'] ?? '0', ENT_QUOTES) ?>">
            </div>
        </fieldset>

        <fieldset class="settings-group">
            <legend>Favicon и PWA</legend>
            <?= \App\Core\AdminUi::imageField('favicon_url', $settings['favicon_url'] ?? '', [
                'label' => 'Favicon (.svg/.png)',
                'file' => 'favicon_file',
                'accept' => 'image/png,image/svg+xml',
            ]) ?>
            <div class="form-field">
                <label for="pwa_short_name">Короткое имя приложения (до 12 символов)</label>
                <input type="text" id="pwa_short_name" name="pwa_short_name" maxlength="12" value="<?= htmlspecialchars($settings['pwa_short_name'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="form-field">
                <label for="theme_color">Theme Color (HEX)</label>
                <input type="text" id="theme_color" name="theme_color" value="<?= htmlspecialchars($settings['theme_color'] ?? '#1a1a1a', ENT_QUOTES) ?>" placeholder="#1a1a1a">
            </div>
        </fieldset>

        <fieldset class="settings-group">
            <legend>Глобальное SEO и соцсети</legend>
            <div class="form-field">
                <label for="default_meta_description">Meta Description по умолчанию</label>
                <input type="text" id="default_meta_description" name="default_meta_description" value="<?= htmlspecialchars($settings['default_meta_description'] ?? '', ENT_QUOTES) ?>">
            </div>
            <?= \App\Core\AdminUi::imageField('default_og_image', $settings['default_og_image'] ?? '', [
                'label' => 'OG:Image по умолчанию',
                'file' => 'default_og_image_file',
            ]) ?>
            <span class="form-hint">Ссылки на соцсети (Telegram/YouTube/VK/Instagram) настраиваются в разделе «Шапка сайта».</span>
        </fieldset>

        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
            <label for="maintenance_mode">Режим обслуживания (сайт закрыт для гостей, админам доступен)</label>
        </div>
        <div class="form-field">
            <label for="maintenance_message">Сообщение на странице обслуживания</label>
            <input type="text" id="maintenance_message" name="maintenance_message" value="<?= htmlspecialchars($settings['maintenance_message'] ?? '', ENT_QUOTES) ?>" placeholder="Сайт временно закрыт на техническое обслуживание.">
        </div>

        <fieldset class="settings-group">
            <legend>Произвольный код сайта (группа 6)</legend>
            <p class="form-hint">Глобальные CSS/JS для всего сайта вне блоков. Доступно только супер-администратору; подключается один раз на каждой странице.</p>
            <div class="form-field">
                <label for="custom_css_global">Глобальный CSS</label>
                <textarea id="custom_css_global" name="custom_css_global" rows="5" style="font-family:monospace;"><?= htmlspecialchars($settings['custom_css_global'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <div class="form-field">
                <label for="custom_js_global">Глобальный JS (без тегов &lt;script&gt;)</label>
                <textarea id="custom_js_global" name="custom_js_global" rows="5" style="font-family:monospace;"><?= htmlspecialchars($settings['custom_js_global'] ?? '', ENT_QUOTES) ?></textarea>
                <span class="form-hint">Выполняется в браузере посетителя. Вставляйте только доверенный код.</span>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить настройки</button>
        </div>
    </form>
</div>

<div class="form-card" style="margin-top:24px;">
    <h2 style="margin-top:0;">Резервное копирование</h2>
    <p class="form-hint">Скачать полный бэкап (дамп базы данных + загруженные файлы) одним архивом.</p>
    <form method="post" action="/admin/backup">
        <?= Csrf::field() ?>
        <button type="submit" class="btn">Скачать бэкап (.zip)</button>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
