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
<?php
$footerStyle = ($designVals['footer_style'] ?? 'columns');
$phone = Setting::get('contact_phone', '');
$email = Setting::get('contact_email', '');
$address = Setting::get('contact_address', '');
$footerLang = \App\Core\Locale::current();
$footerMenu = [];
try { $footerMenu = \App\Models\MenuItem::activeForLang($footerLang); } catch (\Throwable $e) {}
$footerSocial = $hcfg['social_buttons'] ?? [];
?>
<?php if ($footerStyle === 'columns'): ?>
<footer class="site-footer site-footer--columns">
    <div class="site-footer__inner">
        <div class="site-footer__col site-footer__col--brand">
            <?php if ($logo !== ''): ?>
                <img class="site-footer__logo" src="<?= htmlspecialchars($logo, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES) ?>">
            <?php else: ?>
                <div class="site-footer__name"><?= htmlspecialchars($siteName, ENT_QUOTES) ?></div>
            <?php endif; ?>
            <?php if ($address !== ''): ?><p class="site-footer__line"><?= htmlspecialchars($address, ENT_QUOTES) ?></p><?php endif; ?>
            <?php if ($phone !== ''): ?><p class="site-footer__line"><a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone) ?? '', ENT_QUOTES) ?>"><?= htmlspecialchars($phone, ENT_QUOTES) ?></a></p><?php endif; ?>
            <?php if ($email !== ''): ?><p class="site-footer__line"><a href="mailto:<?= htmlspecialchars($email, ENT_QUOTES) ?>"><?= htmlspecialchars($email, ENT_QUOTES) ?></a></p><?php endif; ?>
        </div>
        <?php if (!empty($footerMenu)): ?>
            <nav class="site-footer__col site-footer__col--menu" aria-label="Меню в подвале">
                <div class="site-footer__heading">Разделы</div>
                <ul>
                    <?php foreach ($footerMenu as $mi): ?>
                        <li><a href="<?= htmlspecialchars(\App\Models\MenuItem::resolveUrl($mi, $footerLang), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $mi['title'], ENT_QUOTES) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>
        <?php if (!empty($footerSocial) || $privacyUrl !== ''): // пустую колонку не показываем ?>
        <div class="site-footer__col site-footer__col--contact">
            <div class="site-footer__heading">Связь</div>
            <?php if (!empty($footerSocial)): ?>
                <div class="site-footer__social">
                    <?php foreach ($footerSocial as $btn): ?>
                        <a class="site-footer__social-link" href="<?= htmlspecialchars((string) $btn['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars((string) $btn['network'], ENT_QUOTES) ?>"><?= htmlspecialchars(mb_strtoupper(mb_substr((string) $btn['network'], 0, 1)), ENT_QUOTES) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($privacyUrl !== ''): ?><p class="site-footer__line"><a href="<?= htmlspecialchars($privacyUrl, ENT_QUOTES) ?>">Политика конфиденциальности</a></p><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="site-footer__bottom">&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES) ?></div>
</footer>
<?php else: ?>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES) ?></p>
</footer>
<?php endif; ?>
<?php endif; ?>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/a11y.js'), ENT_QUOTES) ?>" defer></script>
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
