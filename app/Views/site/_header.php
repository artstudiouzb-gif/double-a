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
// Гос-тема (по утверждённым эскизам): navy #173a63 + бирюзовый #17999b,
// типографика PT Serif (заголовки) / PT Sans (текст) — см. gov-theme.css.
$primaryColor = Setting::get('color_primary', '#173a63');
$accentColor = Setting::get('color_accent', '#17999b');
$semanticColors = \App\Core\DesignSettings::semanticColors();
$semanticSpacings = \App\Core\DesignSettings::semanticSpacings();
$font = Setting::get('font_family', "'PT Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif");
$fontHeading = Setting::get('font_heading', "'PT Serif', Georgia, 'Times New Roman', serif");
$extraHeadCss = $extraHeadCss ?? '';

// --- Дизайн-система: тема и локальный шрифт ---
$defaultTheme = Setting::get('default_theme', 'light'); // light | dark | auto
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

// --- Логотип ---
$logoHtml = '<a href="' . htmlspecialchars(Locale::url('/', $currentLang), ENT_QUOTES) . '" class="site-header__logo">';
if ($logo !== '') {
    $logoHtml .= '<img class="site-header__logo-std" src="' . htmlspecialchars($logo, ENT_QUOTES) . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES) . '">';
    // Светлый вариант логотипа для прозрачной шапки (задаётся в конструкторе):
    // сначала — для текущего языка, иначе — общий.
    $logoLight = trim((string) ($hcfgAll['logo_light_by_lang'][$currentLang] ?? ''));
    if ($logoLight === '') {
        $logoLight = trim((string) ($hcfgAll['logo_light'] ?? ''));
    }
    if ($logoLight !== '') {
        $logoHtml .= '<img class="site-header__logo-light" src="' . htmlspecialchars($logoLight, ENT_QUOTES) . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES) . '">';
    }
} else {
    $logoHtml .= '<span>' . htmlspecialchars($siteName, ENT_QUOTES) . '</span>';
}
$logoHtml .= '</a>';

// --- Меню ---
// Инлайновая SVG-иконка пункта: очищается санитайзером при сохранении, здесь
// выводится как есть внутри отдельного span (для стилизации размера/цвета).
$renderMenuIcon = static function (mixed $svg): string {
    $svg = trim((string) $svg);
    return $svg !== '' ? '<span class="site-menu__icon" aria-hidden="true">' . $svg . '</span>' : '';
};
$menuHtml = '';
$et = static fn (string $text): string => htmlspecialchars(t($text), ENT_QUOTES);
$menuItems = MenuItem::activeForLang($currentLang);
if (!empty($menuItems)) {
    $menuHtml = '<nav class="site-menu" aria-label="' . $et('Основное меню') . '">';
    foreach ($menuItems as $mi) {
        // Пункт-разделитель: визуальная черта/зазор без ссылки.
        if (!empty($mi['is_divider'])) {
            $menuHtml .= '<span class="site-menu__divider" role="separator" aria-hidden="true"></span>';
            continue;
        }

        $url = MenuItem::resolveUrl($mi, $currentLang);
        $icon = $renderMenuIcon($mi['icon_svg'] ?? '');
        $label = $icon . '<span class="site-menu__text">' . htmlspecialchars($mi['title'], ENT_QUOTES) . '</span>';

        // Показываем только активные дочерние пункты (разделители в подменю не поддерживаем).
        $children = array_values(array_filter(
            $mi['children'] ?? [],
            static fn ($c) => (int) $c['is_active'] === 1 && empty($c['is_divider'])
        ));

        if ($children === []) {
            $menuHtml .= '<a class="site-menu__link" href="' . htmlspecialchars($url, ENT_QUOTES) . '">'
                . $label . '</a>';
            continue;
        }

        // Пункт с выпадающим подменю (dropdown на desktop hover/focus, tap на мобильных).
        // mega_columns 2..4 — широкая панель в несколько колонок вместо столбца.
        $megaCols = MenuItem::megaColumns($mi['mega_columns'] ?? 0);
        $menuHtml .= '<div class="site-menu__item site-menu__item--has-children'
            . ($megaCols > 0 ? ' site-menu__item--mega' : '') . '">';
        $menuHtml .= '<a class="site-menu__link" href="' . htmlspecialchars($url, ENT_QUOTES) . '">'
            . $label . '</a>';
        $menuHtml .= '<button type="button" class="site-menu__toggle" aria-expanded="false" aria-label="' . $et('Открыть подменю') . '">▾</button>';
        $menuHtml .= $megaCols > 0
            ? '<div class="site-submenu site-submenu--mega" style="--mega-cols:' . $megaCols . ';">'
            : '<div class="site-submenu">';
        foreach ($children as $child) {
            $childUrl = MenuItem::resolveUrl($child, $currentLang);
            $childIcon = $renderMenuIcon($child['icon_svg'] ?? '');
            $menuHtml .= '<a class="site-submenu__link" href="' . htmlspecialchars($childUrl, ENT_QUOTES) . '">'
                . $childIcon . '<span class="site-menu__text">' . htmlspecialchars($child['title'], ENT_QUOTES) . '</span></a>';
        }
        $menuHtml .= '</div></div>';
    }
    $menuHtml .= '</nav>';
}

// --- Переключатель языков ---
// Переключатель всегда показывает все активные языки. Наличие перевода не
// должно лишать посетителя возможности явно сменить язык интерфейса.
$langHtml = '';
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
        $href = Locale::url($path, $code) . '?' . \App\Core\LocalePreference::QUERY . '=' . rawurlencode($code);
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
            . \App\Core\SocialIcons::glyph((string) $btn['network']) . '</a>';
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

// --- Бургер для мобильного меню ---
$burgerHtml = $menuHtml !== ''
    ? '<button type="button" class="site-burger" data-mobile-menu-toggle aria-label="' . $et('Меню') . '" aria-expanded="false"><span></span><span></span><span></span></button>'
    : '';

// --- Макет шапки: 4 варианта ---
// stacked  — верхний ряд + полноширинная навигационная полоса под ним;
// centered — логотип по центру, меню центрировано полосой ниже;
// inline   — логотип, меню и утилиты в одном ряду;
// drawer   — меню скрыто за кнопкой, выезжает off-canvas сбоку (все экраны).
// Прозрачная шапка: глобальный режим применяется только на страницах,
// где включён флаг «Прозрачная шапка» (переменная $transparentHeader из вью).
$transparentOn = !empty($hcfg['transparent']) && !empty($transparentHeader ?? null);
$layout = in_array($hcfg['layout'] ?? 'stacked', HeaderConfig::LAYOUTS, true) ? $hcfg['layout'] : 'stacked';
// Центрированный макет всегда ставит логотип по центру.
$logoPos = $layout === 'centered' ? 'center' : $hcfg['logo_position'];
$navAlign = $layout === 'centered' ? 'center'
    : (in_array($hcfg['menu_position'], ['left', 'center', 'right'], true) ? $hcfg['menu_position'] : 'left');

// --- Раскладка по зонам верхнего ряда ---
// Конструктор: элементы-«кирпичики» расставляются по зонам согласно
// header_config.elements. Логотип и бургер размещаются отдельно.
$phoneVal = trim((string) ($hcfg['contacts']['phone'] ?? ''));
$emailVal = trim((string) ($hcfg['contacts']['email'] ?? ''));
$phoneHtml = $phoneVal !== ''
    ? '<a class="hdr-contact" href="tel:' . htmlspecialchars(preg_replace('/[^+\d]/', '', $phoneVal) ?? '', ENT_QUOTES) . '">'
        . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>'
        . htmlspecialchars($phoneVal, ENT_QUOTES) . '</a>'
    : '';
$emailHtml = $emailVal !== ''
    ? '<a class="hdr-contact" href="mailto:' . htmlspecialchars($emailVal, ENT_QUOTES) . '">'
        . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>'
        . htmlspecialchars($emailVal, ENT_QUOTES) . '</a>'
    : '';
$snippetHtml = (string) ($hcfg['snippet'] ?? ''); // очищен санитайзером при сохранении
$fragments = [
    'search' => $searchHtml,
    'language' => $langHtml,
    'social' => $socialHtml,
    'button' => $ctaHtml,
    'theme' => $themeToggle,
    'a11y' => $a11yToggle,
    'phone' => $phoneHtml,
    'email' => $emailHtml,
    'snippet' => $snippetHtml !== '' ? '<span class="hdr-snippet">' . $snippetHtml . '</span>' : '',
    'divider' => '<span class="site-header__divider" aria-hidden="true"></span>',
    'spacer' => '<span class="site-header__spacer" aria-hidden="true"></span>',
    'space' => '<span class="site-header__space" aria-hidden="true"></span>',
];

// --- Pro Max: верхняя утилитарная полоса (top section) ---
$topbarHtml = '';
if (!empty($hcfg['topbar']['enabled'])) {
    $tbZones = '';
    foreach (['left', 'center', 'right'] as $z) {
        $inner = '';
        foreach ((array) ($hcfg['topbar']['zones'][$z] ?? []) as $el) {
            $inner .= $fragments[$el] ?? '';
        }
        $tbZones .= '<div class="site-topbar__zone site-topbar__zone--' . $z . '">' . $inner . '</div>';
    }
    $tbStyle = in_array($hcfg['topbar']['style'] ?? 'navy', HeaderConfig::BAR_STYLES, true) ? $hcfg['topbar']['style'] : 'navy';
    $tbMobile = !empty($hcfg['topbar']['show_mobile']) ? ' site-topbar--mobile-on' : '';
    $tbHeight = ' site-topbar--h-' . (in_array($hcfg['topbar']['height'] ?? 'normal', HeaderConfig::HEIGHTS, true) ? $hcfg['topbar']['height'] : 'normal');
    $topbarHtml = '<div class="site-topbar site-topbar--' . $tbStyle . $tbMobile . $tbHeight . '"><div class="site-topbar__inner">' . $tbZones . '</div></div>';
}

// --- Pro Max: элементы нижней полосы (bottom section, рядом с меню) ---
$bottomExtras = ['left' => '', 'center' => '', 'right' => ''];
$hasBottomExtras = false;
foreach (['left', 'center', 'right'] as $z) {
    foreach ((array) ($hcfg['bottombar']['zones'][$z] ?? []) as $el) {
        $frag = $fragments[$el] ?? '';
        if ($frag !== '') {
            $bottomExtras[$z] .= $frag;
            $hasBottomExtras = true;
        }
    }
}
// Собираем зоны отдельно для десктопа и мобильного (разные наборы элементов);
// на фронте нужный вариант показывается по media-запросу.
$composeZone = static function (string $zone, string $variant) use ($hcfg, $fragments): string {
    $html = '';
    foreach ($hcfg[$variant][$zone] ?? [] as $el) {
        $html .= $fragments[$el] ?? '';
    }
    return $html;
};
$composed = ['left' => '', 'center' => '', 'right' => ''];
foreach (['left', 'center', 'right'] as $zone) {
    $desktop = $composeZone($zone, 'elements');
    $mobile = $composeZone($zone, 'elements_mobile');
    if ($desktop !== '') {
        $composed[$zone] .= '<span class="hdr-util hdr-util--desktop">' . $desktop . '</span>';
    }
    if ($mobile !== '') {
        $composed[$zone] .= '<span class="hdr-util hdr-util--mobile">' . $mobile . '</span>';
    }
}

$zones = ['left' => '', 'center' => '', 'right' => ''];
$zones['left'] .= $burgerHtml;
$zones[$logoPos] .= $logoHtml;
$zones['left'] .= $composed['left'];
$zones['center'] .= $composed['center'];
$zones['right'] .= $composed['right'];

// Размещение меню зависит от макета:
//  - inline: внутрь центральной зоны верхнего ряда;
//  - stacked/centered: отдельной полосой под верхним рядом;
//  - drawer: в off-canvas панель (рендерится ниже), в шапке — кнопка.
$inlineMenu = '';
$navBarHtml = '';
$drawerMenu = '';
if ($menuHtml !== '') {
    if ($layout === 'inline') {
        $inlineMenu = $menuHtml;
    } elseif ($layout === 'drawer') {
        $drawerMenu = $menuHtml;
    } else {
        $navBarHtml = $menuHtml;
    }
}
// В inline-макете меню занимает центральную зону; но если логотип по центру,
// он уже там — тогда меню уходит в полосу под рядом.
if ($inlineMenu !== '') {
    if ($logoPos === 'center') {
        $navBarHtml = $inlineMenu;
        $inlineMenu = '';
    } else {
        $zones['center'] .= $inlineMenu;
    }
}
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
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/gov-fonts.css'), ENT_QUOTES) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/frontend.css'), ENT_QUOTES) ?>">
<?php
$siteTemplate = \App\Models\Setting::get('design_site_template', 'gov');
if ($siteTemplate === 'double_a'): ?>
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/double-a-theme.css'), ENT_QUOTES) ?>">
<?php else: ?>
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/gov-theme.css'), ENT_QUOTES) ?>">
<?php if ($siteTemplate === 'modern_gov'): ?>
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Asset::url('/assets/css/modern-gov-theme.css'), ENT_QUOTES) ?>">
<?php endif; ?>
<?php endif; ?>
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
<body class="<?= htmlspecialchars(trim($designBodyClass . (!empty($previewNotice) ? ' is-preview' : '') . (!empty($isStaticPage) ? ' page-static' : '')), ENT_QUOTES) ?>">
<a href="#main-content" class="skip-link"><?= $et('Перейти к содержимому') ?></a>
<?php if (!empty($previewNotice)): ?>
<div class="preview-bar" role="status">
    👁 <?= $et('Режим предпросмотра — эта версия не опубликована и закрыта от индексации.') ?>
</div>
<?php endif; ?>
<?php if (empty($hideChrome)): // лендинг (группа 6) скрывает шапку сайта ?>
<?php if ($siteTemplate === 'double_a'): ?>
  <header class="header">
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
        <div class="lang-menu" id="langMenu">
          <?php foreach ($activeLangs as $l): ?>
            <button data-lang="<?= htmlspecialchars((string) $l['code'], ENT_QUOTES) ?>"><?= htmlspecialchars($l['name'], ENT_QUOTES) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if ($hcfg['cta']['enabled'] && $hcfg['cta']['text'] !== ''): ?>
        <a class="btn ink" href="<?= htmlspecialchars($hcfg['cta']['url'] !== '' ? $hcfg['cta']['url'] : '#contact', ENT_QUOTES) ?>">
          <?= htmlspecialchars($hcfg['cta']['text'], ENT_QUOTES) ?>
        </a>
      <?php endif; ?>
      <button class="menu-btn" id="menuBtn" aria-label="Menu">☰</button>
    </div>
  </header>
<?php else: ?>
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
<?= $topbarHtml ?>
<?php
// Свои фоны секций и тень шапки (конструктор шапки). Цвета прошли hex-валидацию
// в HeaderConfig; тень не включается в прозрачном режиме (пока шапка наложена).
$midBg = (string) ($hcfg['middlebar']['bg'] ?? '');
$navBg = (string) ($hcfg['bottombar']['bg'] ?? '');
$shadowOn = !empty($hcfg['shadow']['enabled']) && !$transparentOn;
$headerExtraClass = ($midBg !== '' ? ' site-header--mid-bg' : '')
    . ($navBg !== '' ? ' site-header--nav-bg' : '')
    . ($shadowOn ? ' site-header--shadow' : '');
$headerExtraVars = ($midBg !== '' ? '--header-mid-bg:' . $midBg . ';' : '')
    . ($navBg !== '' ? '--header-nav-bg:' . $navBg . ';' : '')
    . ($shadowOn ? '--header-shadow-size:' . (int) ($hcfg['shadow']['size'] ?? 14) . 'px;' : '');
?>
<header class="site-header site-header--layout-<?= htmlspecialchars($layout, ENT_QUOTES) ?> site-header--logo-<?= htmlspecialchars($logoPos, ENT_QUOTES) ?><?= $navBarHtml !== '' ? ' site-header--has-nav' : '' ?><?= $drawerMenu !== '' ? ' site-header--has-drawer' : '' ?><?= !empty($hcfg['sticky']) ? ' site-header--sticky' : '' ?><?= $transparentOn ? ' site-header--transparent' : '' ?> site-header--h-<?= htmlspecialchars(in_array($hcfg['middlebar']['height'] ?? 'normal', HeaderConfig::HEIGHTS, true) ? $hcfg['middlebar']['height'] : 'normal', ENT_QUOTES) ?> site-header--nav-h-<?= htmlspecialchars(in_array($hcfg['bottombar']['height'] ?? 'normal', HeaderConfig::HEIGHTS, true) ? $hcfg['bottombar']['height'] : 'normal', ENT_QUOTES) ?> site-header--borders-<?= htmlspecialchars(in_array($hcfg['borders'] ?? 'full', HeaderConfig::BORDER_MODES, true) ? $hcfg['borders'] : 'full', ENT_QUOTES) ?><?= $headerExtraClass ?>" style="--header-logo-width:<?= (int) ($hcfg['logo_width'] ?? 240) ?>px;--header-logo-height:<?= (int) ($hcfg['logo_height'] ?? 48) ?>px;<?= $headerExtraVars ?>"<?= (!empty($hcfg['sticky']) || $transparentOn) ? ' data-header-scroll' : '' ?>>
    <div class="site-header__inner">
        <div class="site-header__zone site-header__zone--left"><?= $zones['left'] ?></div>
        <div class="site-header__zone site-header__zone--center"><?= $zones['center'] ?></div>
        <div class="site-header__zone site-header__zone--right"><?= $zones['right'] ?></div>
    </div>
    <?php if ($navBarHtml !== '' || $hasBottomExtras): ?>
    <div class="site-nav site-nav--align-<?= htmlspecialchars($navAlign, ENT_QUOTES) ?><?= $hasBottomExtras ? ' site-nav--with-extras' : '' ?>">
        <div class="site-nav__inner">
            <?php if ($bottomExtras['left'] !== ''): ?><span class="site-nav__extra site-nav__extra--left"><?= $bottomExtras['left'] ?></span><?php endif; ?>
            <?= $navBarHtml ?>
            <?php if ($bottomExtras['center'] !== ''): ?><span class="site-nav__extra site-nav__extra--center"><?= $bottomExtras['center'] ?></span><?php endif; ?>
            <?php if ($bottomExtras['right'] !== ''): ?><span class="site-nav__extra site-nav__extra--right"><?= $bottomExtras['right'] ?></span><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</header>
<?php if ($drawerMenu !== ''): ?>
<?php // Off-canvas меню вынесено за пределы <header>, чтобы position:fixed не
      // зависел от containing block шапки (sticky/трансформации). ?>
<div class="site-drawer" data-drawer>
    <div class="site-drawer__backdrop" data-mobile-menu-toggle aria-hidden="true"></div>
    <div class="site-drawer__panel" role="dialog" aria-label="<?= $et('Меню') ?>" aria-modal="true">
        <button type="button" class="site-drawer__close" data-mobile-menu-toggle aria-label="<?= $et('Закрыть меню') ?>">&times;</button>
        <?= $drawerMenu ?>
    </div>
</div>
<?php endif; ?>
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
