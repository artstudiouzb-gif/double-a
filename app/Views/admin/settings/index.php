<?php

use App\Core\Csrf;

$pageTitle = 'Настройки';
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

        <div class="settings-design-link">
            <strong>Цвета, шрифты и тема оформления</strong>
            <span>Теперь настраиваются в одном месте — в разделе «Дизайн сайта».</span>
            <a href="/admin/design" class="btn btn--small">Открыть управление дизайном</a>
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
            <legend>Брендинг панели управления</legend>
            <div class="form-field">
                <label for="admin_brand_name">Название панели</label>
                <input type="text" id="admin_brand_name" name="admin_brand_name" maxlength="60"
                       value="<?= htmlspecialchars($settings['admin_brand_name'] ?? '', ENT_QUOTES) ?>"
                       placeholder="<?= \App\Core\AdminBrand::DEFAULT_NAME ?>">
                <span class="form-hint">Показывается в шапке админки, заголовке вкладки и на странице входа. Пусто — «<?= \App\Core\AdminBrand::DEFAULT_NAME ?>».</span>
            </div>
            <?= \App\Core\AdminUi::imageField('admin_brand_logo', $settings['admin_brand_logo'] ?? '', [
                'label' => 'Логотип панели',
                'file' => 'admin_brand_logo_file',
            ]) ?>
            <span class="form-hint">Заменяет буквенный бейдж в шапке админки и на странице входа. Лучше всего — горизонтальный логотип на прозрачном фоне (SVG или PNG).</span>
            <div class="form-field">
                <label for="admin_brand_accent">Акцентный цвет панели</label>
                <input type="color" id="admin_brand_accent" name="admin_brand_accent" style="width:64px;height:38px;padding:4px;"
                       value="<?= htmlspecialchars(\App\Core\AdminBrand::accent(), ENT_QUOTES) ?>">
                <span class="form-hint">Кнопки, ссылки и активные пункты меню админки. Стандартный — синий <?= \App\Core\AdminBrand::DEFAULT_ACCENT ?>; оттенки (hover, подсветка) вычисляются автоматически.</span>
            </div>
        </fieldset>

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
            <div class="form-field">
                <label for="telegram_notify_chat_ids">Уведомления о заявках форм: chat_id получателей</label>
                <input type="text" id="telegram_notify_chat_ids" name="telegram_notify_chat_ids" value="<?= htmlspecialchars($settings['telegram_notify_chat_ids'] ?? '', ENT_QUOTES) ?>" placeholder="123456789, -1001234567890" autocomplete="off" spellcheck="false">
                <span class="form-hint">Каждая заявка с форм сайта мгновенно приходит сообщением от бота на эти chat_id (через запятую; отрицательный id — групповой чат, куда добавлен бот). Свой chat_id виден в «Профиле» после привязки Telegram. Пусто — уведомления выключены.</span>
            </div>
        </fieldset>

        <fieldset class="settings-group">
            <legend>Push-уведомления в браузере</legend>
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="webpush_enabled" name="webpush_enabled" value="1" <?= ($settings['webpush_enabled'] ?? '') === '1' ? 'checked' : '' ?>>
                <label for="webpush_enabled">Предлагать посетителям push-уведомления о новостях</label>
            </div>
            <span class="form-hint">
                В блоке подписки футера появится кнопка «Уведомления о новостях».
                При публикации новости уведомление рассылается воркером
                <code>app/Console/push_worker.php</code> (Cron, как social_worker).
                Ключи VAPID генерируются автоматически. Нужен HTTPS.
                Подписчиков сейчас: <strong><?= \App\Models\WebPushSubscription::count() ?></strong>.
            </span>
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
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="form_consent_enabled" name="form_consent_enabled" value="1" <?= ($settings['form_consent_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                <label for="form_consent_enabled">Требовать согласие на обработку персональных данных во всех публичных формах</label>
            </div>
            <div class="form-field">
                <label for="form_consent_text">Текст согласия</label>
                <input type="text" id="form_consent_text" name="form_consent_text" maxlength="500" value="<?= htmlspecialchars($settings['form_consent_text'] ?? 'Я согласен на обработку персональных данных', ENT_QUOTES) ?>">
                <span class="form-hint">Ссылка на «Политику конфиденциальности» добавляется автоматически, если страница выбрана выше.</span>
            </div>
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="captcha_enabled" name="captcha_enabled" value="1" <?= ($settings['captcha_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                <label for="captcha_enabled">Капча на публичных формах (код с картинки, без внешних сервисов)</label>
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
            <div class="form-field">
                <label for="footer_counters">Счетчики в нижней строке подвала (HTML/JS-код)</label>
                <textarea id="footer_counters" name="footer_counters" rows="6" style="font-family:monospace;" placeholder='Например: <a href="https://www.uz/"><img src="..."></a> или код Яндекс.Метрики'><?= htmlspecialchars($settings['footer_counters'] ?? '', ENT_QUOTES) ?></textarea>
                <span class="form-hint">Код отображается справа в нижней строке подвала. Поддержаны HTML-счетчики www.uz и Mail.ru, а также скрипты Яндекс.Метрики и Mail.ru.</span>
            </div>
        </fieldset>

        <div class="form-actions form-actions--sticky">
            <button type="submit" class="btn btn--primary"><?= \App\Core\AdminUi::icon('save') ?>Сохранить настройки</button>
        </div>
    </form>
</div>

<div class="form-card" style="margin-top:24px;">
    <h2 style="margin-top:0;">Демо-контент</h2>
    <p class="form-hint">Наполнить сайт примерами: оформленная главная (hero, счётчики, направления, проекты, новости, медиа) с демо-изображениями, новости, документы, вакансии, тендеры, руководство, типовые страницы и меню. Существующие записи не дублируются, отредактированную главную не трогает — повторная загрузка безопасна.</p>
    <form method="post" action="/admin/settings/demo-content" data-confirm="Загрузить демо-контент в разделы сайта?">
        <?= Csrf::field() ?>
        <div class="form-field" style="max-width:360px;">
            <label for="demo_confirm_code">Код подтверждения</label>
            <input type="text" id="demo_confirm_code" name="demo_confirm_code" required autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Введите DEMO">
            <span class="form-hint">Для запуска введите <code>DEMO</code>.</span>
        </div>
        <button type="submit" class="btn btn--primary">Загрузить демо-контент</button>
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
