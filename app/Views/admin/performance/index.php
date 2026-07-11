<?php

use App\Core\Csrf;

$pageTitle = 'Производительность';
$activeNav = 'performance';
require __DIR__ . '/../layout/header.php';

/** @var array $settings */
$val = fn (string $k, string $d = '') => htmlspecialchars($settings[$k] ?? $d, ENT_QUOTES);
$on = fn (string $k, string $d = '0') => ($settings[$k] ?? $d) === '1';
?>
<div class="form-card">
    <form method="post" action="/admin/performance/clear-cache" style="margin-bottom:22px;">
        <?= Csrf::field() ?>
        <div class="header-builder__group" style="margin-bottom:0;">
            <h3>Кэш</h3>
            <p class="form-hint" style="margin-top:0;">
                Скомпилированные страницы кэшируются на диск и сбрасываются автоматически при
                правке контента. Кнопка ниже очищает весь кэш вручную (например, после смены дизайна).
            </p>
            <button type="submit" class="btn btn--primary">Очистить кэш сейчас</button>
        </div>
    </form>

    <form method="post" action="/admin/performance" class="form-grid">
        <?= Csrf::field() ?>

        <div class="header-builder__group">
            <h3>Кэширование страниц</h3>
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="perf_page_cache" name="perf_page_cache" value="1" <?= $on('perf_page_cache', '1') ? 'checked' : '' ?>>
                <label for="perf_page_cache">Кэшировать скомпилированные страницы (рекомендуется на боевом)</label>
            </div>
            <div class="form-field">
                <label for="perf_cache_ttl">Время жизни кэша, секунд (0 — до следующей правки контента)</label>
                <input type="number" id="perf_cache_ttl" name="perf_cache_ttl" min="0" value="<?= $val('perf_cache_ttl', '0') ?>">
                <span class="form-hint">Например, 3600 — пересобирать страницы не чаще раза в час.</span>
            </div>
        </div>

        <div class="header-builder__group">
            <h3>Изображения</h3>
            <div class="form-field">
                <label for="perf_webp_quality">Качество WebP (40–95)</label>
                <input type="number" id="perf_webp_quality" name="perf_webp_quality" min="40" max="95" value="<?= $val('perf_webp_quality', '82') ?>">
                <span class="form-hint">Ниже — легче файлы, выше — качественнее. Применяется к новым загрузкам.</span>
            </div>
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="perf_lazy_load" name="perf_lazy_load" value="1" <?= $on('perf_lazy_load', '1') ? 'checked' : '' ?>>
                <label for="perf_lazy_load">Ленивая загрузка изображений (loading="lazy")</label>
            </div>
        </div>

        <div class="header-builder__group">
            <h3>CDN для статики и загрузок</h3>
            <div class="form-field">
                <label for="perf_cdn_url">Базовый URL CDN (необязательно)</label>
                <input type="text" id="perf_cdn_url" name="perf_cdn_url" value="<?= $val('perf_cdn_url') ?>" placeholder="https://xxxxx.b-cdn.net">
                <span class="form-hint">
                    Ссылки на <code>/assets</code> (CSS/JS) и <code>/uploads/public</code> (картинки, видео)
                    будут отдаваться с этого хоста. <b>Домен переносить в Cloudflare не нужно</b> —
                    подойдёт любой <b>pull-zone CDN</b>: вы указываете origin (этот сайт), а CDN даёт вам
                    свой хост (например, BunnyCDN <code>*.b-cdn.net</code>, KeyCDN, Fastly, либо Cloudflare R2 /
                    поддомен за Cloudflare). CDN сам тянет файлы с сайта и кэширует их по миру.
                    Инвалидация не нужна: статика версионируется (<code>?v=</code>), а имена загрузок уникальны.
                </span>
            </div>
        </div>

        <div class="header-builder__group">
            <h3>Cloudflare (очистка кэша по API)</h3>
            <p class="form-hint" style="margin-top:0;">
                Нужно только если сайт или его поддомен <b>проксируется через Cloudflare</b>
                (оранжевое облако). Тогда при изменении контента кэш Cloudflare очищается автоматически.
                Токен создайте в Cloudflare → My Profile → API Tokens с правом <code>Zone · Cache Purge</code>.
            </p>
            <label class="hb-switch" style="margin-bottom:10px;">
                <input type="checkbox" name="cf_enabled" value="1" <?= $val('cf_enabled') === '1' ? 'checked' : '' ?>>
                <span class="hb-switch__track"></span> Включить интеграцию с Cloudflare
            </label>
            <div class="form-field">
                <label for="cf_api_token">API-токен</label>
                <input type="password" id="cf_api_token" name="cf_api_token" value="<?= $val('cf_api_token') ?>" placeholder="cf_xxx" autocomplete="off">
            </div>
            <div class="form-field">
                <label for="cf_zone_id">Zone ID</label>
                <input type="text" id="cf_zone_id" name="cf_zone_id" value="<?= $val('cf_zone_id') ?>" placeholder="напр. 023e105f4ecef8ad9ca31a8372d0c353">
                <span class="form-hint">Zone ID — на странице обзора домена в панели Cloudflare (справа).</span>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить</button>
        </div>
    </form>

    <?php if (($val('cf_api_token') !== '') && ($val('cf_zone_id') !== '')): ?>
        <div class="header-builder__group" style="margin-top:18px;">
            <h3>Cloudflare — проверка и очистка</h3>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <form method="post" action="/admin/cloudflare/verify" style="margin:0;">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn">Проверить подключение</button>
                </form>
                <form method="post" action="/admin/cloudflare/purge" style="margin:0;">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn--primary">Очистить кэш Cloudflare</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
