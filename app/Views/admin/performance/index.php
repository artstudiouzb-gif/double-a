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
            <h3>CDN</h3>
            <div class="form-field">
                <label for="perf_cdn_url">Базовый URL CDN для статики (необязательно)</label>
                <input type="text" id="perf_cdn_url" name="perf_cdn_url" value="<?= $val('perf_cdn_url') ?>" placeholder="https://cdn.example.com">
                <span class="form-hint">Если задан — версионированные ссылки на <code>/assets</code> и <code>/uploads</code> будут отдаваться с этого домена.</span>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
