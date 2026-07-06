<?php

use App\Models\Setting;

$siteName = Setting::get('site_name', 'ArtStudio');
/** @var string $canonicalUrl — задаётся в _header.php (та же область видимости View) */
$printUrl = $canonicalUrl ?? '';

// --- Аналитика + Cookie-Consent (задача 116) ---
$analyticsInit = \App\Core\Analytics::hasAny() ? \App\Core\Analytics::initScript() : '';
$consentRequired = Setting::get('cookie_consent_enabled', '0') === '1';
$privacyUrl = '';
$privacyPageId = (int) Setting::get('privacy_policy_page_id', '');
if ($privacyPageId > 0) {
    $pp = \App\Models\Page::findById($privacyPageId);
    if ($pp && ($pp['status'] ?? '') === 'published') {
        $privacyUrl = \App\Core\Locale::url($pp['slug']);
    }
}
?>
<div class="print-only print-footer">
    <?php if ($printUrl !== ''): ?>Источник: <?= htmlspecialchars($printUrl, ENT_QUOTES) ?> &nbsp;·&nbsp; <?php endif; ?>
    &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES) ?>
</div>
</main>
<?php if (empty($hideChrome)): // лендинг (группа 6) скрывает футер сайта ?>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES) ?></p>
</footer>
<?php endif; ?>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/frontend.js'), ENT_QUOTES) ?>"></script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/forms.js'), ENT_QUOTES) ?>" defer></script>
<?= \App\Core\AssetCollector::renderScripts() /* JS блоков — по одному разу */ ?>
<?php if ($analyticsInit !== ''): ?>
<?php // Код счётчиков инертен (type text/plain); consent.js активирует его. ?>
<script type="text/plain" id="analytics-init"><?= $analyticsInit ?></script>
<script>window.__consent = {required: <?= $consentRequired ? 'true' : 'false' ?>, privacyUrl: <?= json_encode($privacyUrl, JSON_UNESCAPED_SLASHES) ?>};</script>
<script src="/assets/js/consent.js" defer></script>
<?php endif; ?>
<?php // Глобальный произвольный JS (группа 6, супер-админ). ?>
<?php $globalJs = Setting::get('custom_js_global', ''); ?>
<?php if (trim($globalJs) !== ''): ?>
<script><?= $globalJs ?></script>
<?php endif; ?>
</body>
</html>
