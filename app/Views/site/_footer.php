<?php

use App\Models\Setting;

/** @var string $logo */
$logo = $logo ?? '';
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
    <?php if ($printUrl !== ''): ?><?= htmlspecialchars(t('Источник:'), ENT_QUOTES) ?> <?= htmlspecialchars($printUrl, ENT_QUOTES) ?> &nbsp;·&nbsp; <?php endif; ?>
    &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES) ?>
</div>
</main>
<?php if (empty($hideChrome)): // лендинг (группа 6) скрывает футер сайта ?>
<?php
$siteTemplate = \App\Models\Setting::get('design_site_template', 'gov');
if ($siteTemplate === 'double_a'): ?>
  <footer class="footer">
    <div class="wrap footer-grid">
      <div>
        <a class="brand" href="<?= htmlspecialchars(\App\Core\Locale::url('/', $currentLang ?? null), ENT_QUOTES) ?>" style="margin-bottom:20px;display:flex">
          <span class="brandmark" aria-hidden="true" style="background:#fff"></span>
          <span style="color:#fff"><strong>DOUBLE A SOLUTIONS</strong><small style="color:rgba(255,255,255,.5)">MARKET · COMPLIANCE · GROWTH</small></span>
        </a>
        <p style="font-size:11px;color:rgba(255,255,255,.55);max-width:320px">Регуляторный консалтинг, сопровождение выхода на рынок Узбекистана и развитие экспорта.</p>
      </div>
      <div>
        <h4>Услуги</h4>
        <a href="/#service-market">Выход на рынок</a>
        <a href="/#service-permits">Разрешительные документы</a>
        <a href="/#service-export">Экспортное сопровождение</a>
        <a href="/#service-iso">Стандарты качества</a>
      </div>
      <div>
        <h4>Отрасли</h4>
        <a href="/#industries">Сельское хозяйство</a>
        <a href="/#industries">Пищевой бизнес</a>
        <a href="/#industries">Химическая отрасль</a>
        <a href="/#industries">Косметика и БАД</a>
      </div>
      <div>
        <h4>Компания</h4>
        <a href="/o-nas">О нас</a>
        <a href="/projects">Кейсы</a>
        <a href="/press-centr">База знаний</a>
        <a href="/kontakty">Контакты</a>
      </div>
    </div>
    <div class="wrap footer-bottom">
      <span>&copy; <?= date('Y') ?> DOUBLE A SOLUTIONS. All rights reserved.</span>
      <span>Created by <a href="https://artstudio.uz" target="_blank" style="display:inline;color:inherit;text-decoration:underline">ArtStudio</a></span>
    </div>
  </footer>

  <!-- Global Modal Popup -->
  <div class="modal" id="infoModal">
    <div class="modal-card">
        <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        <h2 id="modalTitle">Консультация</h2>
        <div id="modalContent"></div>
        <div style="margin-top:28px">
            <a class="btn ink" href="/kontakty" onclick="closeModal()">Обсудить проект</a>
        </div>
    </div>
  </div>

  <!-- Global Cookie Bar -->
  <div class="cookie" id="cookieBar">
    <p>Мы используем файлы cookie для анализа трафика и улучшения работы сайта. Продолжая использование, вы соглашаетесь с условиями.</p>
    <div class="cookie-actions">
        <button type="button" class="btn primary" id="cookieAccept" style="min-height:38px;padding:0 15px;font-size:11px">Принять</button>
        <button type="button" class="btn ghost" id="cookieDecline" style="min-height:38px;padding:0 15px;font-size:11px;color:var(--navy);border-color:var(--line)">Отклонить</button>
    </div>
  </div>

  <!-- Global Toast -->
  <div class="toast" id="toast"></div>
<?php else: ?>
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

// Логотип подвала: тёмный фон → используем светлый (тёмный) вариант логотипа —
// сначала для текущего языка, затем общий, иначе обычный логотип.
$footerHcfg = \App\Core\HeaderConfig::get();
$footerLogo = trim((string) ($footerHcfg['logo_light_by_lang'][$footerLang] ?? ''));
if ($footerLogo === '') { $footerLogo = trim((string) ($footerHcfg['logo_light'] ?? '')); }
if ($footerLogo === '') { $footerLogo = $logo; }

// Рендер одного виджета колонки подвала.
$renderFooterWidget = function (array $col) use ($footerLogo, $siteName, $address, $phone, $email, $footerMenu, $footerLang, $footerSocial, $privacyUrl): string {
    switch ($col['widget']) {
        case 'about':
            $h = '';
            if ($footerLogo !== '') {
                $h .= '<img class="site-footer__logo" src="' . htmlspecialchars($footerLogo, ENT_QUOTES) . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES) . '">';
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
            if ($privacyUrl !== '') { $h .= '<p class="site-footer__line"><a href="' . htmlspecialchars($privacyUrl, ENT_QUOTES) . '">' . htmlspecialchars(t('Политика конфиденциальности'), ENT_QUOTES) . '</a></p>'; }
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
            return '<p class="site-footer__line">' . htmlspecialchars(t('Будьте в курсе наших новостей и аналитических материалов.'), ENT_QUOTES) . '</p>'
                . '<form class="footer-subscribe" method="post" action="/subscribe">'
                . \App\Core\Csrf::field()
                . '<div style="position:absolute;left:-9999px;" aria-hidden="true"><input type="text" name="hp_website" tabindex="-1" autocomplete="off"></div>'
                . '<input type="hidden" name="hp_ts" value="' . htmlspecialchars($ts, ENT_QUOTES) . '">'
                . '<input type="email" name="email" placeholder="' . htmlspecialchars(t('Ваш e-mail'), ENT_QUOTES) . '" aria-label="E-mail" required>'
                . '<button type="submit" aria-label="' . htmlspecialchars(t('Подписаться'), ENT_QUOTES) . '">&rarr;</button>'
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
    <div class="site-footer__bottom">
        <div class="site-footer__bottom-inner">
            <div class="site-footer__bottom-col site-footer__bottom-col--left">
                <?= htmlspecialchars($footerBottom, ENT_QUOTES) ?>
            </div>
            <div class="site-footer__bottom-col site-footer__bottom-col--middle">
                <?php if ($privacyUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($privacyUrl, ENT_QUOTES) ?>"><?= htmlspecialchars(t('Политика конфиденциальности'), ENT_QUOTES) ?></a>
                <?php endif; ?>
            </div>
            <div class="site-footer__bottom-col site-footer__bottom-col--right">
                <?php $footerCounters = \App\Core\SecurityHeaders::injectScriptNonce((string) Setting::get('footer_counters', '')); ?>
                <?php if (trim($footerCounters) !== ''): ?>
                    <div class="site-footer__counters">
                        <?= $footerCounters ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>
<?php else: ?>
<footer class="site-footer">
    <div class="site-footer__bottom">
        <div class="site-footer__bottom-inner">
            <div class="site-footer__bottom-col site-footer__bottom-col--left">
                <?= htmlspecialchars($footerBottom, ENT_QUOTES) ?>
            </div>
            <div class="site-footer__bottom-col site-footer__bottom-col--middle">
                <?php if ($privacyUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($privacyUrl, ENT_QUOTES) ?>"><?= htmlspecialchars(t('Политика конфиденциальности'), ENT_QUOTES) ?></a>
                <?php endif; ?>
            </div>
            <div class="site-footer__bottom-col site-footer__bottom-col--right">
                <?php $footerCounters = \App\Core\SecurityHeaders::injectScriptNonce((string) Setting::get('footer_counters', '')); ?>
                <?php if (trim($footerCounters) !== ''): ?>
                    <div class="site-footer__counters">
                        <?= $footerCounters ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php // Плавающая кнопка «Наверх» — видимостью управляет класс body.design-scrolltop
      // (тумблер в «Дизайн») и JS (появляется после прокрутки). ?>
<button type="button" class="scroll-top" data-scroll-top aria-label="<?= htmlspecialchars(t('Наверх'), ENT_QUOTES) ?>" title="<?= htmlspecialchars(t('Наверх'), ENT_QUOTES) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
</button>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/a11y.js'), ENT_QUOTES) ?>" defer></script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/frontend.js'), ENT_QUOTES) ?>" defer></script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/forms.js'), ENT_QUOTES) ?>" defer></script>
<?php if (\App\Models\Setting::get('design_site_template', 'gov') === 'double_a'): ?>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/double-a.js'), ENT_QUOTES) ?>" defer></script>
<?php endif; ?>
<?php $cspNonce = \App\Core\SecurityHeaders::nonce(); ?>
<?php if (\App\Core\WebPush::isEnabled()): ?>
<script nonce="<?= $cspNonce ?>">window.__pushEnabled = true; window.__pushLabels = <?= json_encode([
    'off' => t('Уведомления о новостях'),
    'on' => t('Уведомления включены'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;</script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/push.js'), ENT_QUOTES) ?>" defer></script>
<?php endif; ?>
<?= \App\Core\AssetCollector::renderScripts() /* JS блоков — по одному разу */ ?>
<?php if ($analyticsInit !== ''): ?>
<?php // Код счётчиков инертен (type text/plain); consent.js активирует его,
      // перенося nonce с держателя на создаваемый <script> (CSP). ?>
<script type="text/plain" id="analytics-init" nonce="<?= $cspNonce ?>"><?= $analyticsInit ?></script>
<script nonce="<?= $cspNonce ?>">window.__consent = {required: <?= $consentRequired ? 'true' : 'false' ?>, privacyUrl: <?= json_encode($privacyUrl, JSON_UNESCAPED_SLASHES) ?>};</script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/consent.js'), ENT_QUOTES) ?>" defer></script>
<?php endif; ?>
<?php // Schema.org: карточка организации — только на главной (JSON-LD валиден в body). ?>
<?php if (\App\Core\Locale::path() === '/'): ?>
<?= \App\Core\SchemaOrg::render(\App\Core\SchemaOrg::organization(
    $siteName,
    \App\Core\AppUrl::base() ?: '/',
    Setting::get('contact_phone', ''),
    Setting::get('contact_email', ''),
    Setting::get('contact_address', ''),
    $logo !== '' ? \App\Core\AppUrl::base() . $logo : ''
)) . "\n" ?>
<?php endif; ?>
<?php // Глобальный произвольный JS (группа 6, супер-админ). ?>
<?php $globalJs = Setting::get('custom_js_global', ''); ?>
<?php if (trim($globalJs) !== ''): ?>
<script nonce="<?= $cspNonce ?>"><?= $globalJs ?></script>
<?php endif; ?>
</body>
</html>
