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

$siteName = Setting::get('site_name', 'ArtStudio');
$logo = Setting::get('logo_url', '');
$primaryColor = Setting::get('color_primary', '#1a1a1a');
$accentColor = Setting::get('color_accent', '#e63946');
$font = Setting::get('font_family', "'Inter', sans-serif");
$extraHeadCss = $extraHeadCss ?? '';

// --- Дизайн-система: тема и локальный шрифт ---
$defaultTheme = Setting::get('default_theme', 'light'); // light | dark | auto
if (!in_array($defaultTheme, ['light', 'dark', 'auto'], true)) {
    $defaultTheme = 'light';
}
$fontUrl = Setting::get('font_url', '');           // ссылка на .woff2 локального шрифта
$fontFaceName = Setting::get('font_face_name', ''); // имя семейства для @font-face

// --- SEO / Open Graph ---
$appUrl = rtrim((string) \App\Core\Config::get('app.url', ''), '/');
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

// --- Логотип ---
$logoHtml = '<a href="' . htmlspecialchars(Locale::url('/', $currentLang), ENT_QUOTES) . '" class="site-header__logo">';
if ($logo !== '') {
    $logoHtml .= '<img src="' . htmlspecialchars($logo, ENT_QUOTES) . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES) . '">';
} else {
    $logoHtml .= '<span>' . htmlspecialchars($siteName, ENT_QUOTES) . '</span>';
}
$logoHtml .= '</a>';

// --- Меню ---
$menuHtml = '';
$menuItems = MenuItem::activeForLang($currentLang);
if (!empty($menuItems)) {
    $menuHtml = '<nav class="site-menu">';
    foreach ($menuItems as $mi) {
        $url = MenuItem::resolveUrl($mi, $currentLang);
        // Показываем только активные дочерние пункты.
        $children = array_values(array_filter(
            $mi['children'] ?? [],
            static fn ($c) => (int) $c['is_active'] === 1
        ));

        if ($children === []) {
            $menuHtml .= '<a class="site-menu__link" href="' . htmlspecialchars($url, ENT_QUOTES) . '">'
                . htmlspecialchars($mi['title'], ENT_QUOTES) . '</a>';
            continue;
        }

        // Пункт с выпадающим подменю (dropdown на desktop hover/focus, tap на мобильных).
        $menuHtml .= '<div class="site-menu__item site-menu__item--has-children">';
        $menuHtml .= '<a class="site-menu__link" href="' . htmlspecialchars($url, ENT_QUOTES) . '">'
            . htmlspecialchars($mi['title'], ENT_QUOTES) . '</a>';
        $menuHtml .= '<button type="button" class="site-menu__toggle" aria-expanded="false" aria-label="Открыть подменю">▾</button>';
        $menuHtml .= '<div class="site-submenu">';
        foreach ($children as $child) {
            $childUrl = MenuItem::resolveUrl($child, $currentLang);
            $menuHtml .= '<a class="site-submenu__link" href="' . htmlspecialchars($childUrl, ENT_QUOTES) . '">'
                . htmlspecialchars($child['title'], ENT_QUOTES) . '</a>';
        }
        $menuHtml .= '</div></div>';
    }
    $menuHtml .= '</nav>';
}

// --- Переключатель языков ---
$langHtml = '';
$activeLangs = Language::active();
if ($hcfg['language_switcher']['enabled'] && count($activeLangs) > 1) {
    $flags = ['ru' => '🇷🇺', 'uz' => '🇺🇿', 'en' => '🇬🇧', 'kk' => '🇰🇿', 'tr' => '🇹🇷', 'de' => '🇩🇪'];
    $format = $hcfg['language_switcher']['format'];
    $path = Locale::path();
    $langHtml = '<div class="site-lang-switcher">';
    foreach ($activeLangs as $l) {
        $code = (string) $l['code'];
        $label = match ($format) {
            'name' => $l['name'],
            'flag' => $flags[$code] ?? strtoupper($code),
            default => strtoupper($code),
        };
        $href = Locale::url($path, $code);
        $isActive = $code === $currentLang ? ' is-active' : '';
        $langHtml .= '<a class="site-lang-switcher__item' . $isActive . '" href="' . htmlspecialchars($href, ENT_QUOTES) . '">' . htmlspecialchars((string) $label, ENT_QUOTES) . '</a>';
    }
    $langHtml .= '</div>';
}

// --- Кнопки соцсетей ---
$socialHtml = '';
if (!empty($hcfg['social_buttons'])) {
    $socialHtml = '<div class="site-social">';
    foreach ($hcfg['social_buttons'] as $btn) {
        $socialHtml .= '<a class="site-social__link site-social__link--' . htmlspecialchars($btn['network'], ENT_QUOTES) . '" href="'
            . htmlspecialchars($btn['url'], ENT_QUOTES) . '" target="_blank" rel="noopener" aria-label="' . htmlspecialchars($btn['network'], ENT_QUOTES) . '">'
            . htmlspecialchars(mb_strtoupper(mb_substr($btn['network'], 0, 1)), ENT_QUOTES) . '</a>';
    }
    $socialHtml .= '</div>';
}

// --- CTA-кнопка ---
$ctaHtml = '';
if ($hcfg['cta']['enabled'] && $hcfg['cta']['text'] !== '') {
    $ctaHtml = '<a class="site-cta site-cta--' . htmlspecialchars($hcfg['cta']['style'], ENT_QUOTES) . '" href="'
        . htmlspecialchars($hcfg['cta']['url'] !== '' ? $hcfg['cta']['url'] : '#', ENT_QUOTES) . '">'
        . htmlspecialchars($hcfg['cta']['text'], ENT_QUOTES) . '</a>';
}

// --- Переключатель темы (показываем, если тема не фиксирована как auto) ---
$themeToggle = '';
if ($defaultTheme !== 'auto') {
    $themeToggle = '<button type="button" class="site-theme-toggle" aria-label="Сменить тему" title="Светлая/тёмная тема">◐</button>';
}

// --- Версия для слабовидящих: состояние из cookie (без JS-мигания) ---
$a11ySchemes = ['cw', 'wc', 'bb'];
$a11ySizes = ['m', 'l', 'xl'];
$a11yParts = explode(':', (string) ($_COOKIE['a11y'] ?? ''));
$a11y = [
    'on' => in_array($a11yParts[0] ?? '', $a11ySchemes, true),
    'scheme' => in_array($a11yParts[0] ?? '', $a11ySchemes, true) ? $a11yParts[0] : 'cw',
    'size' => in_array($a11yParts[1] ?? '', $a11ySizes, true) ? $a11yParts[1] : 'm',
    'images' => ($a11yParts[2] ?? '') === 'off' ? 'off' : 'on',
];
$a11yToggle = '<button type="button" class="a11y-toggle" aria-label="Версия для слабовидящих" title="Версия для слабовидящих">'
    . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>'
    . '<span>Для слабовидящих</span></button>';

// --- Поиск по сайту (в строке + иконка для выпадающего режима) ---
$searchAction = htmlspecialchars(Locale::url('search', $currentLang), ENT_QUOTES);
$searchIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>';
$searchHtml = '<form class="site-search" method="get" action="' . $searchAction . '" role="search">'
    . '<input type="search" name="q" placeholder="Поиск" aria-label="Поиск по сайту">'
    . '<button type="submit" aria-label="Найти">' . $searchIcon . '</button></form>'
    . '<button type="button" class="site-search-toggle" aria-label="Открыть поиск" aria-expanded="false" data-search-toggle>' . $searchIcon . '</button>';

// --- Тема-билдер: значения дизайна + классы для <body> ---
$designVals = \App\Core\DesignSettings::current();
$designBodyClass = \App\Core\DesignSettings::bodyClasses($designVals);

// --- Бургер для мобильного меню ---
$burgerHtml = $menuHtml !== ''
    ? '<button type="button" class="site-burger" data-mobile-menu-toggle aria-label="Меню" aria-expanded="false"><span></span><span></span><span></span></button>'
    : '';

// --- Раскладка по зонам ---
$zones = ['left' => '', 'center' => '', 'right' => ''];
$zones['left'] .= $burgerHtml;
$zones[$hcfg['logo_position']] .= $logoHtml;
$zones[$hcfg['menu_position']] .= $menuHtml;
// Утилиты (язык, соцсети, CTA, тема) — в правую зону.
$zones['right'] .= $searchHtml . $langHtml . $socialHtml . $ctaHtml . $themeToggle . $a11yToggle;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang, ENT_QUOTES) ?>" data-theme="<?= htmlspecialchars($defaultTheme, ENT_QUOTES) ?>"<?= $a11y['on'] ? ' data-a11y="1" data-a11y-scheme="' . htmlspecialchars($a11y['scheme'], ENT_QUOTES) . '" data-a11y-size="' . htmlspecialchars($a11y['size'], ENT_QUOTES) . '" data-a11y-images="' . htmlspecialchars($a11y['images'], ENT_QUOTES) . '"' : '' ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>
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
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/frontend.css'), ENT_QUOTES) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/a11y.css'), ENT_QUOTES) ?>">
<style>
:root {
    --color-primary: <?= htmlspecialchars($primaryColor, ENT_QUOTES) ?>;
    --color-accent: <?= htmlspecialchars($accentColor, ENT_QUOTES) ?>;
    --font-family: <?= htmlspecialchars($font, ENT_QUOTES) ?>;
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
<body class="<?= htmlspecialchars(trim($designBodyClass . (!empty($previewNotice) ? ' is-preview' : '')), ENT_QUOTES) ?>">
<a href="#main-content" class="skip-link">Перейти к содержимому</a>
<?php if (!empty($previewNotice)): ?>
<div class="preview-bar" role="status">
    👁 Режим предпросмотра — эта версия не опубликована и закрыта от индексации.
</div>
<?php endif; ?>
<?php if (empty($hideChrome)): // лендинг (группа 6) скрывает шапку сайта ?>
<div class="a11y-panel<?= $a11y['on'] ? ' is-open' : '' ?>" role="region" aria-label="Настройки версии для слабовидящих">
    <div class="a11y-panel__group">
        <b>Цвет:</b>
        <button type="button" data-a11y-set="scheme:cw" title="Чёрным по белому">Ч</button>
        <button type="button" data-a11y-set="scheme:wc" title="Белым по чёрному">Б</button>
        <button type="button" data-a11y-set="scheme:bb" title="Тёмно-синим по голубому">С</button>
    </div>
    <div class="a11y-panel__group">
        <b>Размер:</b>
        <button type="button" class="a11y-panel__size-a1" data-a11y-set="size:m" title="Обычный">А</button>
        <button type="button" class="a11y-panel__size-a2" data-a11y-set="size:l" title="Крупный">А</button>
        <button type="button" class="a11y-panel__size-a3" data-a11y-set="size:xl" title="Очень крупный">А</button>
    </div>
    <div class="a11y-panel__group">
        <b>Изображения:</b>
        <button type="button" data-a11y-set="images:on" title="Показывать">Вкл</button>
        <button type="button" data-a11y-set="images:off" title="Скрыть">Выкл</button>
    </div>
    <a href="#" class="a11y-panel__off">Обычная версия</a>
</div>
<header class="site-header site-header--logo-<?= htmlspecialchars($hcfg['logo_position'], ENT_QUOTES) ?>">
    <div class="site-header__inner">
        <div class="site-header__zone site-header__zone--left"><?= $zones['left'] ?></div>
        <div class="site-header__zone site-header__zone--center"><?= $zones['center'] ?></div>
        <div class="site-header__zone site-header__zone--right"><?= $zones['right'] ?></div>
    </div>
</header>
<div class="site-search-overlay" data-search-overlay hidden>
    <form class="site-search-overlay__form" method="get" action="<?= $searchAction ?>" role="search">
        <input type="search" name="q" placeholder="Введите запрос…" aria-label="Поиск по сайту" data-search-input>
        <button type="submit" class="site-search-overlay__submit">Найти</button>
        <button type="button" class="site-search-overlay__close" aria-label="Закрыть поиск" data-search-close>&times;</button>
    </form>
</div>
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
