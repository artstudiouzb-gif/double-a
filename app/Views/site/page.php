<?php

use App\Models\Setting;

/** @var array $page */
/** @var string $content */
/** @var string $blockCss */

$siteName = Setting::get('site_name', 'ArtStudio');
$logo = Setting::get('logo_url', '');
$primaryColor = Setting::get('color_primary', '#1a1a1a');
$accentColor = Setting::get('color_accent', '#e63946');
$font = Setting::get('font_family', "'Inter', sans-serif");
$counterCodes = Setting::get('counter_codes', '');

$metaTitle = $page['meta_title'] ?: $page['title'];
$metaDescription = $page['meta_description'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($metaTitle, ENT_QUOTES) ?></title>
<?php if ($metaDescription !== ''): ?>
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
<?php if ($blockCss !== ''): ?>
<style id="block-styles">
<?= $blockCss ?>
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
    </div>
</header>
<main class="site-content">
<?= $content ?>
</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES) ?></p>
</footer>
<?= $counterCodes /* коды счётчиков вводятся администратором в настройках */ ?>
</body>
</html>
