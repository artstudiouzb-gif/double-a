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
$footerCfg = \App\Core\FooterConfig::get();
$footerStyle = $footerCfg['style'];
$phone = Setting::get('contact_phone', '');
$email = Setting::get('contact_email', '');
$address = Setting::get('contact_address', '');
$footerLang = \App\Core\Locale::current();
$footerMenu = [];
try { $footerMenu = \App\Models\MenuItem::activeForLang($footerLang); } catch (\Throwable $e) {}
$footerSocial = $hcfg['social_buttons'] ?? [];
$footerBottom = \App\Core\FooterConfig::renderBottom($footerCfg['bottom'], $siteName);

// Рендер одного виджета колонки подвала.
$renderFooterWidget = function (array $col) use ($logo, $siteName, $address, $phone, $email, $footerMenu, $footerLang, $footerSocial, $privacyUrl): string {
    switch ($col['widget']) {
        case 'about':
            $h = '';
            if ($logo !== '') {
                $h .= '<img class="site-footer__logo" src="' . htmlspecialchars($logo, ENT_QUOTES) . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES) . '">';
            } else {
                $h .= '<div class="site-footer__name">' . htmlspecialchars($siteName, ENT_QUOTES) . '</div>';
            }
            if ($address !== '') { $h .= '<p class="site-footer__line">' . htmlspecialchars($address, ENT_QUOTES) . '</p>'; }
            if ($phone !== '') { $h .= '<p class="site-footer__line"><a href="tel:' . htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone) ?? '', ENT_QUOTES) . '">' . htmlspecialchars($phone, ENT_QUOTES) . '</a></p>'; }
            if ($email !== '') { $h .= '<p class="site-footer__line"><a href="mailto:' . htmlspecialchars($email, ENT_QUOTES) . '">' . htmlspecialchars($email, ENT_QUOTES) . '</a></p>'; }
            return $h;
        case 'menu':
            if (empty($footerMenu)) { return ''; }
            $h = '<ul>';
            foreach ($footerMenu as $mi) {
                if (!empty($mi['is_divider'])) { continue; }
                $h .= '<li><a href="' . htmlspecialchars(\App\Models\MenuItem::resolveUrl($mi, $footerLang), ENT_QUOTES) . '">' . htmlspecialchars((string) $mi['title'], ENT_QUOTES) . '</a></li>';
            }
            return $h . '</ul>';
        case 'contacts':
            $h = '';
            if ($address !== '') { $h .= '<p class="site-footer__line">' . htmlspecialchars($address, ENT_QUOTES) . '</p>'; }
            if ($phone !== '') { $h .= '<p class="site-footer__line"><a href="tel:' . htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone) ?? '', ENT_QUOTES) . '">' . htmlspecialchars($phone, ENT_QUOTES) . '</a></p>'; }
            if ($email !== '') { $h .= '<p class="site-footer__line"><a href="mailto:' . htmlspecialchars($email, ENT_QUOTES) . '">' . htmlspecialchars($email, ENT_QUOTES) . '</a></p>'; }
            if ($privacyUrl !== '') { $h .= '<p class="site-footer__line"><a href="' . htmlspecialchars($privacyUrl, ENT_QUOTES) . '">Политика конфиденциальности</a></p>'; }
            return $h;
        case 'social':
            if (empty($footerSocial)) { return ''; }
            $h = '<div class="site-footer__social">';
            foreach ($footerSocial as $btn) {
                $h .= '<a class="site-footer__social-link" href="' . htmlspecialchars((string) $btn['url'], ENT_QUOTES) . '" target="_blank" rel="noopener" aria-label="' . htmlspecialchars((string) $btn['network'], ENT_QUOTES) . '">' . \App\Core\SocialIcons::glyph((string) $btn['network']) . '</a>';
            }
            return $h . '</div>';
        case 'subscribe':
            // Форма подписки в подвале (постит в /subscribe, как и блок).
            $ts = (string) time();
            return '<p class="site-footer__line">Будьте в курсе наших новостей и аналитических материалов.</p>'
                . '<form class="footer-subscribe" method="post" action="/subscribe">'
                . \App\Core\Csrf::field()
                . '<div style="position:absolute;left:-9999px;" aria-hidden="true"><input type="text" name="hp_website" tabindex="-1" autocomplete="off"></div>'
                . '<input type="hidden" name="hp_ts" value="' . htmlspecialchars($ts, ENT_QUOTES) . '">'
                . '<input type="email" name="email" placeholder="Ваш e-mail" aria-label="E-mail" required>'
                . '<button type="submit" aria-label="Подписаться">&rarr;</button>'
                . '</form>'
                . '<div data-push-optin></div>'; // сюда push.js добавляет кнопку уведомлений
        case 'text':
            // Уже очищено санитайзером при сохранении.
            return '<div class="site-footer__text">' . $col['text'] . '</div>';
        default:
            return '';
    }
};
?>
<?php if ($footerStyle === 'columns' && !empty($footerCfg['columns'])): ?>
<footer class="site-footer site-footer--columns">
    <div class="site-footer__inner">
        <?php foreach ($footerCfg['columns'] as $col): ?>
            <?php $inner = $renderFooterWidget($col); ?>
            <?php if ($inner === '' && $col['widget'] !== 'text') { continue; } // пустые колонки скрываем ?>
            <div class="site-footer__col site-footer__col--<?= htmlspecialchars($col['widget'], ENT_QUOTES) ?>">
                <?php if ($col['heading'] !== ''): ?><div class="site-footer__heading"><?= htmlspecialchars($col['heading'], ENT_QUOTES) ?></div><?php endif; ?>
                <?= $inner ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="site-footer__bottom"><?= htmlspecialchars($footerBottom, ENT_QUOTES) ?></div>
</footer>
<?php else: ?>
<footer class="site-footer">
    <p><?= htmlspecialchars($footerBottom, ENT_QUOTES) ?></p>
</footer>
<?php endif; ?>
<?php endif; ?>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/a11y.js'), ENT_QUOTES) ?>" defer></script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/frontend.js'), ENT_QUOTES) ?>"></script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/forms.js'), ENT_QUOTES) ?>" defer></script>
<?php $cspNonce = \App\Core\SecurityHeaders::nonce(); ?>
<?php if (\App\Core\WebPush::isEnabled()): ?>
<script nonce="<?= $cspNonce ?>">window.__pushEnabled = true;</script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/push.js'), ENT_QUOTES) ?>" defer></script>
<?php endif; ?>
<?= \App\Core\AssetCollector::renderScripts() /* JS блоков — по одному разу */ ?>
<?php if ($analyticsInit !== ''): ?>
<?php // Код счётчиков инертен (type text/plain); consent.js активирует его,
      // перенося nonce с держателя на создаваемый <script> (CSP). ?>
<script type="text/plain" id="analytics-init" nonce="<?= $cspNonce ?>"><?= $analyticsInit ?></script>
<script nonce="<?= $cspNonce ?>">window.__consent = {required: <?= $consentRequired ? 'true' : 'false' ?>, privacyUrl: <?= json_encode($privacyUrl, JSON_UNESCAPED_SLASHES) ?>};</script>
<script src="/assets/js/consent.js" defer></script>
<?php endif; ?>
<?php // Schema.org: карточка организации — только на главной (JSON-LD валиден в body). ?>
<?php if (\App\Core\Locale::path() === '/'): ?>
<?= \App\Core\SchemaOrg::render(\App\Core\SchemaOrg::organization(
    $siteName,
    rtrim((string) \App\Core\Config::get('app.url', ''), '/') ?: '/',
    Setting::get('contact_phone', ''),
    Setting::get('contact_email', ''),
    Setting::get('contact_address', ''),
    $logo !== '' ? rtrim((string) \App\Core\Config::get('app.url', ''), '/') . $logo : ''
)) . "\n" ?>
<?php endif; ?>
<?php // Глобальный произвольный JS (группа 6, супер-админ). ?>
<?php $globalJs = Setting::get('custom_js_global', ''); ?>
<?php if (trim($globalJs) !== ''): ?>
<script nonce="<?= $cspNonce ?>"><?= $globalJs ?></script>
<?php endif; ?>
</body>
</html>
