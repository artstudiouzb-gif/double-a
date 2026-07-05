<?php

use App\Core\Flash;
use App\Models\Setting;

/** @var string $metaTitle */
/** @var string $metaDescription */
/** @var string $extraHeadCss */

$siteName = Setting::get('site_name', 'ArtStudio');
$logo = Setting::get('logo_url', '');
$primaryColor = Setting::get('color_primary', '#1a1a1a');
$accentColor = Setting::get('color_accent', '#e63946');
$font = Setting::get('font_family', "'Inter', sans-serif");
$extraHeadCss = $extraHeadCss ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($metaTitle, ENT_QUOTES) ?></title>
<?php if (!empty($metaDescription)): ?>
<meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES) ?>">
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/frontend.css">
<style>
:root {
    --color-primary: <?= htmlspecialchars($primaryColor, ENT_QUOTES) ?>;
    --color-accent: <?= htmlspecialchars($accentColor, ENT_QUOTES) ?>;
    --font-family: <?= htmlspecialchars($font, ENT_QUOTES) ?>;
}
</style>
<?php if ($extraHeadCss !== ''): ?>
<style id="block-styles">
<?= $extraHeadCss ?>
</style>
<?php endif; ?>
</head>
<body>
<header class="site-header">
    <div class="site-header__inner">
        <a href="/" class="site-header__logo">
            <?php if ($logo !== ''): ?>
                <img src="<?= htmlspecialchars($logo, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES) ?>">
            <?php else: ?>
                <span><?= htmlspecialchars($siteName, ENT_QUOTES) ?></span>
            <?php endif; ?>
        </a>
        <nav class="site-header__nav">
            <a href="/news">Новости</a>
        </nav>
    </div>
</header>
<main class="site-content">
<?php foreach (Flash::pull() as $flash): ?>
    <div class="site-alert site-alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES) ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES) ?>
    </div>
<?php endforeach; ?>
