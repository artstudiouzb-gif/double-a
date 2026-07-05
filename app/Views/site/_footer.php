<?php

use App\Models\Setting;

$siteName = Setting::get('site_name', 'ArtStudio');
$counterCodes = Setting::get('counter_codes', '');
/** @var string $canonicalUrl — задаётся в _header.php (та же область видимости View) */
$printUrl = $canonicalUrl ?? '';
?>
<div class="print-only print-footer">
    <?php if ($printUrl !== ''): ?>Источник: <?= htmlspecialchars($printUrl, ENT_QUOTES) ?> &nbsp;·&nbsp; <?php endif; ?>
    &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES) ?>
</div>
</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES) ?></p>
</footer>
<script src="/assets/js/frontend.js"></script>
<script src="/assets/js/forms.js" defer></script>
<?= \App\Core\AssetCollector::renderScripts() /* JS блоков — по одному разу */ ?>
<?= $counterCodes /* коды счётчиков вводятся администратором в настройках */ ?>
</body>
</html>
