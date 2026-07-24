<?php

use App\Core\Flash;
use App\Core\HeaderConfig;
use App\Core\Locale;
use App\Models\Language;
use App\Models\MenuItem;
use App\Models\Setting;

/** @var string $metaTitle */
/** @var string $metaDescription */
/** @var string $extraHeadCss */
/** @var string $ogImage */
/** @var string $ogType */
/** @var array<int, string> $preloadImages */

$siteName = Setting::get('site_name', 'ArtStudio');
// Логотип: переопределение на текущий язык (Шапка → логотип для языка),
// иначе общий логотип из Настроек.
$hcfgAll = \App\Core\HeaderConfig::get();
$logo = trim((string) ($hcfgAll['logo_by_lang'][\App\Core\Locale::current()] ?? ''));
if ($logo === '') {
    $logo = (string) Setting::get('logo_url', '');
}
// Тема Double A: изумруд #062c37 + золото #d5ae62, типографика
// Noto Serif Condensed (заголовки) / Noto Sans (текст) — см. da-modern.css.
$primaryColor = Setting::get('color_primary', '#062c37');
$accentColor = Setting::get('color_accent', '#d5ae62');
$semanticColors = \App\Core\DesignSettings::semanticColors();
$semanticSpacings = \App\Core\DesignSettings::semanticSpacings();
$font = Setting::get('font_family', "'PT Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif");
$fontHeading = Setting::get('font_heading', "'PT Serif', Georgia, 'Times New Roman', serif");
$extraHeadCss = $extraHeadCss ?? '';

// --- Дизайн-система: тема и локальный шрифт ---
// Double A — тёмная тема по умолчанию (светлая доступна переключателем).
$defaultTheme = Setting::get('default_theme', 'dark'); // light | dark | auto
if (!in_array($defaultTheme, ['light', 'dark', 'auto'], true)) {
    $defaultTheme = 'light';
}
$fontUrl = Setting::get('font_url', '');           // ссылка на .woff2 локального шрифта
$fontFaceName = Setting::get('font_face_name', ''); // имя семейства для @font-face
// Не загружаем сохранённый локальный файл, когда выбран базовый или Google-шрифт.
if (\App\Core\DesignSettings::bodyFontChoice() !== 'style:custom') {
    $fontUrl = '';
    $fontFaceName = '';
}

// --- SEO / Open Graph ---
$appUrl = \App\Core\AppUrl::base();
$canonicalUrl = $appUrl . Locale::url(Locale::path());
$ogType = $ogType ?? 'website';
// Приоритет OG-картинки: страница -> дефолтный OG:Image -> логотип (задача 116).
$defaultOg = Setting::get('default_og_image', '');
$ogImageRaw = ($ogImage ?? '') !== '' ? $ogImage : ($defaultOg !== '' ? $defaultOg : ($logo !== '' ? $logo : ''));
// Абсолютный URL для og:image.
if ($ogImageRaw !== '' && !preg_match('#^https?://#', $ogImageRaw)) {
    $ogImageRaw = $appUrl . '/' . ltrim($ogImageRaw, '/');
}
// Meta Description по умолчанию, если не задан на странице.
if (empty($metaDescription)) {
    $metaDescription = Setting::get('default_meta_description', '');
}
// Favicon / Theme Color / PWA (задача 116).
$faviconUrl = Setting::get('favicon_url', '');
$themeColor = Setting::get('theme_color', '');
$pwaShortName = Setting::get('pwa_short_name', '');

$hcfg = HeaderConfig::get();
$currentLang = Locale::current();
$et = static fn (string $text): string => htmlspecialchars(t($text), ENT_QUOTES);
// Пункты меню (шапка double_a выводит их напрямую в своей вёрстке).
$menuItems = MenuItem::activeForLang($currentLang);

// --- Языки (для переключателя в шапке и hreflang) ---
$activeLangs = Language::active();
$hrefLangs = $activeLangs;
$contentLangs = \App\Core\Locale::contentLangs();
if ($contentLangs !== null) {
    // Для SEO по-прежнему объявляем только реально существующие переводы.
    $hrefLangs = array_values(array_filter(
        $hrefLangs,
        static fn (array $l): bool => in_array((string) $l['code'], $contentLangs, true)
    ));
}

// --- Переключатель темы (показываем, если тема не фиксирована как auto) ---
$themeToggle = '';
if ($defaultTheme !== 'auto') {
    $themeToggle = '<button type="button" class="site-theme-toggle" aria-label="' . $et('Сменить тему') . '" title="' . $et('Светлая/тёмная тема') . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 0 0 20V2z" fill="currentColor"/></svg></button>';
}

// --- Версия для слабовидящих: состояние из cookie (без JS-мигания) ---
$a11ySchemes = ['cw', 'wc', 'bb'];
$a11ySizes = ['m', 'l', 'xl'];
$a11yParts = explode(':', (string) ($_COOKIE['a11y'] ?? ''));
$a11y = [
    'on' => in_array($a11yParts[0] ?? '', $a11ySchemes, true),
    'scheme' => in_array($a11yParts[0] ?? '', $a11ySchemes, true) ? $a11yParts[0] : 'cw',
    'size' => in_array($a11yParts[1] ?? '', $a11ySizes, true) ? $a11yParts[1] : 'l',
    'images' => ($a11yParts[2] ?? '') === 'off' ? 'off' : 'on',
];
$a11yToggle = '<button type="button" class="a11y-toggle" aria-label="' . $et('Версия для слабовидящих') . '" title="' . $et('Версия для слабовидящих') . '" aria-controls="a11y-panel" aria-expanded="' . ($a11y['on'] ? 'true' : 'false') . '">'
    . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>'
    . '<span>' . $et('Для слабовидящих') . '</span></button>';

// --- Тема-билдер: значения дизайна + классы для <body> ---
$designVals = \App\Core\DesignSettings::current();
$designBodyClass = \App\Core\DesignSettings::bodyClasses($designVals);
$searchType = ($designVals['search_type'] ?? 'inline') === 'overlay' ? 'overlay' : 'inline';

// --- Поиск по сайту: в шапку выводится только выбранный в дизайне вариант ---
$searchAction = htmlspecialchars(Locale::url('search', $currentLang), ENT_QUOTES);
$searchIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>';
$inlineSearchHtml = '<form class="site-search" method="get" action="' . $searchAction . '" role="search">'
    . '<input type="search" name="q" minlength="2" required autocomplete="off" placeholder="' . htmlspecialchars(t('Поиск'), ENT_QUOTES) . '" aria-label="' . htmlspecialchars(t('Поиск по сайту'), ENT_QUOTES) . '">'
    . '<button type="submit" aria-label="' . $et('Найти') . '">' . $searchIcon . '</button></form>';
$overlaySearchHtml = '<button type="button" class="site-search-toggle" aria-label="' . $et('Открыть поиск') . '" aria-controls="site-search-popover" aria-expanded="false" data-search-toggle>' . $searchIcon . '</button>';
$searchHtml = $searchType === 'overlay' ? $overlaySearchHtml : $inlineSearchHtml;

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang, ENT_QUOTES) ?>" data-theme="<?= htmlspecialchars($defaultTheme, ENT_QUOTES) ?>"<?= $a11y['on'] ? ' data-a11y="1" data-a11y-scheme="' . htmlspecialchars($a11y['scheme'], ENT_QUOTES) . '" data-a11y-size="' . htmlspecialchars($a11y['size'], ENT_QUOTES) . '" data-a11y-images="' . htmlspecialchars($a11y['images'], ENT_QUOTES) . '"' : '' ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script nonce="<?= \App\Core\SecurityHeaders::nonce() ?>">
/* Применяем сохранённую тему до отрисовки, исключая мигание (FOUC). */
(function(){try{var t=localStorage.getItem('theme');if(t){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();
</script>
<title><?= htmlspecialchars($metaTitle, ENT_QUOTES) ?></title>
<?php if (!empty($robotsNoindex)): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if (!empty($metaDescription)): ?>
<meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES) ?>">
<?php // hreflang: текущий путь на языках, где контент реально существует
      // ($hrefLangs отфильтрован по Locale::contentLangs выше),
      // + x-default (основной язык). Одинокий hreflang не выводим. ?>
<?php if (count($hrefLangs) > 1): ?>
<?php foreach ($hrefLangs as $hrefLang): ?>
<link rel="alternate" hreflang="<?= htmlspecialchars((string) $hrefLang['code'], ENT_QUOTES) ?>" href="<?= htmlspecialchars($appUrl . Locale::url(Locale::path(), (string) $hrefLang['code']), ENT_QUOTES) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($appUrl . Locale::url(Locale::path(), \App\Models\Language::defaultCode()), ENT_QUOTES) ?>">
<?php endif; ?>
<link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars($siteName . ' — Новости', ENT_QUOTES) ?>" href="<?= htmlspecialchars(Locale::url('news/rss.xml', $currentLang), ENT_QUOTES) ?>">
<meta property="og:site_name" content="<?= htmlspecialchars($siteName, ENT_QUOTES) ?>">
<meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES) ?>">
<meta property="og:title" content="<?= htmlspecialchars($metaTitle, ENT_QUOTES) ?>">
<?php if (!empty($metaDescription)): ?>
<meta property="og:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES) ?>">
<?php endif; ?>
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES) ?>">
<?php if ($ogImageRaw !== ''): ?>
<meta property="og:image" content="<?= htmlspecialchars($ogImageRaw, ENT_QUOTES) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<?php // Первый Hero уже отрендерен/закэширован до header, поэтому его LCP-кандидат
      // можно начать загружать до блокирующих stylesheet. ?>
<?php foreach (array_slice(array_values(array_unique($preloadImages ?? [])), 0, 1) as $preloadImage): ?>
<?= \App\Core\Media::preloadLink((string) $preloadImage, '100vw') ?>
<?php endforeach; ?>
<?php
$cdnBase = \App\Core\Asset::cdnBase();
$cdnParts = $cdnBase !== '' ? parse_url($cdnBase) : false;
$cdnOrigin = '';
if (is_array($cdnParts) && in_array($cdnParts['scheme'] ?? '', ['http', 'https'], true) && !empty($cdnParts['host'])) {
    $cdnOrigin = $cdnParts['scheme'] . '://' . $cdnParts['host']
        . (isset($cdnParts['port']) ? ':' . (int) $cdnParts['port'] : '');
}
?>
<?php if ($cdnOrigin !== ''): ?>
<link rel="preconnect" href="<?= htmlspecialchars($cdnOrigin, ENT_QUOTES) ?>" crossorigin>
<link rel="dns-prefetch" href="//<?= htmlspecialchars((string) $cdnParts['host'], ENT_QUOTES) ?>">
<?php endif; ?>
<?php if ($fontUrl !== '' && $fontFaceName !== ''): ?>
<link rel="preload" href="<?= htmlspecialchars($fontUrl, ENT_QUOTES) ?>" as="font" type="font/woff2" crossorigin>
<style>
@font-face {
    font-family: '<?= htmlspecialchars($fontFaceName, ENT_QUOTES) ?>';
    src: url('<?= htmlspecialchars($fontUrl, ENT_QUOTES) ?>') format('woff2');
    font-weight: 100 900;
    font-display: swap;
}
</style>
<?php endif; ?>
<?php if ($faviconUrl !== ''): ?>
<link rel="icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES) ?>">
<?php endif; ?>
<?php if ($themeColor !== ''): ?>
<meta name="theme-color" content="<?= htmlspecialchars($themeColor, ENT_QUOTES) ?>">
<?php endif; ?>
<?php if ($pwaShortName !== ''): ?>
<link rel="manifest" href="/manifest.webmanifest">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($pwaShortName, ENT_QUOTES) ?>">
<?php endif; ?>
<?php // Preload только реально выбранных семейств: лишние preload конкурируют с CSS/LCP. ?>
<?php
$fontPreloads = [];
foreach ([(string) $font, (string) $fontHeading] as $selectedFont) {
    foreach ([
        'Manrope' => '/assets/fonts/manrope-400-cyrillic.woff2',
        'Montserrat' => '/assets/fonts/montserrat-700-cyrillic.woff2',
        'PT Serif' => '/assets/fonts/ptserif-700-cyrillic.woff2',
        'PT Sans' => '/assets/fonts/ptsans-400-cyrillic.woff2',
    ] as $family => $fontFile) {
        if (stripos($selectedFont, $family) !== false) {
            $fontPreloads[$fontFile] = true;
        }
    }
}
?>
<?php foreach (array_keys($fontPreloads) as $fontFile): ?>
<link rel="preload" href="<?= htmlspecialchars(\App\Core\Asset::url($fontFile), ENT_QUOTES) ?>" as="font" type="font/woff2" crossorigin>
<?php endforeach; ?>
<?php // Google-шрифты (если выбраны в «Дизайне»); кириллица включена в css2. ?>
<?php $googleFontsHref = \App\Core\DesignSettings::googleFontsHref(); ?>
<?php if ($googleFontsHref !== null): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= htmlspecialchars($googleFontsHref, ENT_QUOTES) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/fonts.css'), ENT_QUOTES) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/frontend.css'), ENT_QUOTES) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/noto-fonts.css'), ENT_QUOTES) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/da-modern.css'), ENT_QUOTES) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/rich-content.css'), ENT_QUOTES) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/a11y.css'), ENT_QUOTES) ?>">
<style>
:root {
    --color-primary: <?= htmlspecialchars($primaryColor, ENT_QUOTES) ?>;
    --color-accent: <?= htmlspecialchars($accentColor, ENT_QUOTES) ?>;
    <?php // Текстовые варианты акцента считаются из него же: «красивый» акцент
          // почти всегда слишком светлый для мелкого текста. ?>
    --gov-teal-text: <?= htmlspecialchars(\App\Core\AccentContrast::onLight($accentColor, $semanticColors['bg_surface']), ENT_QUOTES) ?>;
    --gov-teal-on-dark: <?= htmlspecialchars(\App\Core\AccentContrast::onDark($accentColor), ENT_QUOTES) ?>;
    --bg-primary: <?= htmlspecialchars($semanticColors['bg_primary'], ENT_QUOTES) ?>;
    --bg-surface: <?= htmlspecialchars($semanticColors['bg_surface'], ENT_QUOTES) ?>;
    --text-main: <?= htmlspecialchars($semanticColors['text_main'], ENT_QUOTES) ?>;
    --text-muted: <?= htmlspecialchars($semanticColors['text_muted'], ENT_QUOTES) ?>;
    --border-color: <?= htmlspecialchars($semanticColors['border_color'], ENT_QUOTES) ?>;
    --gov-bg: var(--bg-primary);
    --gov-surface: var(--bg-surface);
    --gov-ink: var(--text-main);
    --gov-muted: var(--text-muted);
    --gov-border: var(--border-color);
    --space-small: <?= htmlspecialchars($semanticSpacings['space_small'], ENT_QUOTES) ?>;
    --space-premium: <?= htmlspecialchars($semanticSpacings['space_premium'], ENT_QUOTES) ?>;
    --space-max: <?= htmlspecialchars($semanticSpacings['space_max'], ENT_QUOTES) ?>;
    <?php // Внутри <style> HTML-экранирование ломает кавычки ('Inter' -> &#039;Inter&#039;).
          // Санитизация под CSS: только буквы/цифры/пробел/запятая/дефис/одинарные кавычки. ?>
    --font-family: <?= preg_replace("/[^a-zA-Z0-9 ,'\\-]/", '', (string) $font) ?: 'system-ui, sans-serif' ?>;
    --font-heading: <?= preg_replace("/[^a-zA-Z0-9 ,'\\-]/", '', (string) $fontHeading) ?: "'Montserrat', system-ui, sans-serif" ?>;
}
<?php // Тема-билдер: переменные дизайна (ширина, скругления, отступы, кнопки). ?>
<?= \App\Core\DesignSettings::cssVariables($designVals) ?>
</style>
<?php if ($extraHeadCss !== ''): ?>
<style id="block-styles">
<?= $extraHeadCss ?>
</style>
<?php endif; ?>
<?php // Глобальный произвольный CSS (группа 6, супер-админ). ?>
<?php $globalCss = Setting::get('custom_css_global', ''); ?>
<?php if (trim($globalCss) !== ''): ?>
<style id="global-custom-css">
<?= $globalCss ?>
</style>
<?php endif; ?>
</head>
<body class="<?= htmlspecialchars(trim($designBodyClass . (!empty($previewNotice) ? ' is-preview' : '') . (!empty($isStaticPage) ? ' page-static' : '') . (!empty($transparentHeader) ? ' has-transparent-header' : '')), ENT_QUOTES) ?>">
<a href="#main-content" class="skip-link"><?= $et('Перейти к содержимому') ?></a>
<?php if (!empty($previewNotice)): ?>
<div class="preview-bar" role="status">
    👁 <?= $et('Режим предпросмотра — эта версия не опубликована и закрыта от индексации.') ?>
</div>
<?php endif; ?>
<?php if (empty($hideChrome)): // лендинг (группа 6) скрывает шапку сайта ?>
  <header class="header<?= !empty($transparentHeader) ? ' header--transparent' : '' ?>"<?= !empty($transparentHeader) ? ' data-header-scroll' : '' ?>>
    <div class="wrap nav">
      <a class="brand" href="<?= htmlspecialchars(Locale::url('/', $currentLang), ENT_QUOTES) ?>" aria-label="DOUBLE A SOLUTIONS">
        <span class="brandmark" aria-hidden="true"></span>
        <span><strong>DOUBLE A SOLUTIONS</strong><small>MARKET · COMPLIANCE · GROWTH</small></span>
      </a>
      <nav class="navlinks" id="navlinks" aria-label="Main navigation">
        <?php foreach ($menuItems as $mi): ?>
          <a href="<?= htmlspecialchars(MenuItem::resolveUrl($mi, $currentLang), ENT_QUOTES) ?>">
            <?= htmlspecialchars($mi['title'], ENT_QUOTES) ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="lang" id="lang">
        <button class="lang-btn" id="langBtn" aria-expanded="false" aria-label="Choose language"><b id="langCode"><?= strtoupper($currentLang) ?></b><span>⌄</span></button>
        <div class="lang-menu" id="langMenu" role="menu">
          <?php $lpath = Locale::path(); foreach ($activeLangs as $l): $lc = (string) $l['code'];
            $lhref = Locale::url($lpath, $lc) . '?' . \App\Core\LocalePreference::QUERY . '=' . rawurlencode($lc); ?>
            <a role="menuitem" hreflang="<?= htmlspecialchars($lc, ENT_QUOTES) ?>" href="<?= htmlspecialchars($lhref, ENT_QUOTES) ?>"<?= $lc === $currentLang ? ' class="is-active" aria-current="true"' : '' ?>><?= htmlspecialchars($l['name'], ENT_QUOTES) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="hdr-tools"><?= $searchHtml ?><?= $themeToggle ?><?= $a11yToggle ?></div>
      <?php if ($hcfg['cta']['enabled'] && $hcfg['cta']['text'] !== ''): ?>
        <a class="btn ink" href="<?= htmlspecialchars($hcfg['cta']['url'] !== '' ? $hcfg['cta']['url'] : '#contact', ENT_QUOTES) ?>">
          <?= htmlspecialchars($hcfg['cta']['text'], ENT_QUOTES) ?>
        </a>
      <?php endif; ?>
      <button class="menu-btn" id="menuBtn" aria-label="Menu">☰</button>
    </div>
  </header>
<div class="a11y-panel<?= $a11y['on'] ? ' is-open' : '' ?>" id="a11y-panel" role="region" aria-label="<?= $et('Настройки версии для слабовидящих') ?>">
    <div class="a11y-panel__group">
        <b><?= $et('Цвет:') ?></b>
        <button type="button" data-a11y-set="scheme:cw" title="<?= $et('Чёрным по белому') ?>">A</button>
        <button type="button" data-a11y-set="scheme:wc" title="<?= $et('Белым по чёрному') ?>">A</button>
        <button type="button" data-a11y-set="scheme:bb" title="<?= $et('Тёмно-синим по голубому') ?>">A</button>
    </div>
    <div class="a11y-panel__group">
        <b><?= $et('Размер:') ?></b>
        <button type="button" class="a11y-panel__size-a1" data-a11y-set="size:m" title="<?= $et('Обычный') ?>">A</button>
        <button type="button" class="a11y-panel__size-a2" data-a11y-set="size:l" title="<?= $et('Крупный') ?>">A</button>
        <button type="button" class="a11y-panel__size-a3" data-a11y-set="size:xl" title="<?= $et('Очень крупный') ?>">A</button>
    </div>
    <div class="a11y-panel__group">
        <b><?= $et('Изображения:') ?></b>
        <button type="button" data-a11y-set="images:on" title="<?= $et('Показывать') ?>"><?= $et('Вкл') ?></button>
        <button type="button" data-a11y-set="images:off" title="<?= $et('Скрыть') ?>"><?= $et('Выкл') ?></button>
    </div>
    <a href="#" class="a11y-panel__off"><?= $et('Обычная версия') ?></a>
</div>
<?php if ($searchType === 'overlay'): ?>
<div class="site-search-overlay" id="site-search-popover" data-search-overlay hidden role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars(t('Поиск по сайту'), ENT_QUOTES) ?>">
    <form class="site-search-overlay__form" method="get" action="<?= $searchAction ?>" role="search">
        <input type="search" name="q" minlength="2" required autocomplete="off" placeholder="<?= htmlspecialchars(t('Введите запрос…'), ENT_QUOTES) ?>" aria-label="<?= htmlspecialchars(t('Поиск по сайту'), ENT_QUOTES) ?>" data-search-input>
        <button type="submit" class="site-search-overlay__submit"><?= $et('Найти') ?></button>
        <button type="button" class="site-search-overlay__close" aria-label="<?= $et('Закрыть поиск') ?>" data-search-close>&times;</button>
    </form>
</div>
<?php endif; ?>
<?php endif; ?>
<main class="site-content" id="main-content">
<div class="print-only print-header">
    <?php if ($logo !== ''): ?>
        <img src="<?= htmlspecialchars($logo, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES) ?>">
    <?php else: ?>
        <div class="print-name"><?= htmlspecialchars($siteName, ENT_QUOTES) ?></div>
    <?php endif; ?>
</div>
<?php foreach (Flash::pull() as $flash): ?>
    <div class="site-alert site-alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES) ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES) ?>
    </div>
<?php endforeach; ?>
