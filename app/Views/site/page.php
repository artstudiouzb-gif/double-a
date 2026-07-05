<?php

/** @var array $page */
/** @var string $content */
/** @var string $blockCss */

$metaTitle = $page['meta_title'] ?: $page['title'];
$metaDescription = $page['meta_description'] ?? '';
$extraHeadCss = $blockCss;
require __DIR__ . '/_header.php';
?>
<?= $content ?>
<?php require __DIR__ . '/_footer.php'; ?>
