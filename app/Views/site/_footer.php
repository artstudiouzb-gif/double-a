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
  <footer class="footer">
    <div class="wrap footer-grid">
      <div>
        <a class="brand" href="<?= htmlspecialchars(\App\Core\Locale::url('/', $currentLang ?? null), ENT_QUOTES) ?>" style="margin-bottom:20px;display:flex">
          <span class="brandmark" aria-hidden="true" style="background:#fff"></span>
          <span style="color:#fff"><strong>DOUBLE A SOLUTIONS</strong><small style="color:rgba(255,255,255,.5)">MARKET · COMPLIANCE · GROWTH</small></span>
        </a>
        <p style="font-size:14px;color:rgba(255,255,255,.55);max-width:320px">Регуляторный консалтинг, сопровождение выхода на рынок Узбекистана и развитие экспорта.</p>
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
        <button type="button" class="btn primary" id="cookieAccept" style="min-height:38px;padding:0 15px;font-size:14px">Принять</button>
        <button type="button" class="btn ghost" id="cookieDecline" style="min-height:38px;padding:0 15px;font-size:14px;color:var(--navy);border-color:var(--line)">Отклонить</button>
    </div>
  </div>

  <!-- Global Toast -->
  <div class="toast" id="toast"></div>
<?php endif; ?>
<?php // Плавающая кнопка «Наверх» — видимостью управляет класс body.design-scrolltop
      // (тумблер в «Дизайн») и JS (появляется после прокрутки). ?>
<button type="button" class="scroll-top" data-scroll-top aria-label="<?= htmlspecialchars(t('Наверх'), ENT_QUOTES) ?>" title="<?= htmlspecialchars(t('Наверх'), ENT_QUOTES) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
</button>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/a11y.js'), ENT_QUOTES) ?>" defer></script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/frontend.js'), ENT_QUOTES) ?>" defer></script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/forms.js'), ENT_QUOTES) ?>" defer></script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/double-a.js'), ENT_QUOTES) ?>" defer></script>
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
