<?php

/** @var array $page */
/** @var string $content */
/** @var string $blockCss */
/** @var string $layoutType */
/** @var array|null $sidebar */

$metaTitle = $page['meta_title'] ?: $page['title'];
$metaDescription = $page['meta_description'] ?? '';
$extraHeadCss = $blockCss;
$hideChrome = !empty($page['hide_chrome']); // лендинг (группа 6)
require __DIR__ . '/_header.php';

$hasSidebar = $sidebar !== null && trim($sidebar['html']) !== '';
?>
<?php if ($hasSidebar): ?>
    <div class="layout layout--<?= htmlspecialchars($sidebar['position'], ENT_QUOTES) ?>">
        <?php if ($sidebar['position'] === 'left'): ?>
            <aside class="layout__sidebar"><?= $sidebar['html'] ?></aside>
            <div class="layout__main"><?= $content ?></div>
        <?php else: ?>
            <div class="layout__main"><?= $content ?></div>
            <aside class="layout__sidebar"><?= $sidebar['html'] ?></aside>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
