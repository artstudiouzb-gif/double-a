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
// Флаг страницы «Прозрачная шапка» — активирует режим из конструктора.
$transparentHeader = !empty($page['transparent_header']);
require __DIR__ . '/_header.php';

// Хлебные крошки для обычных страниц (не главная, не лендинг).
if (empty($page['is_home']) && !$hideChrome) {
    $crumbs = [
        ['label' => 'Главная', 'url' => \App\Core\Locale::url('/')],
        ['label' => (string) ($page['title'] ?? '')],
    ];
    require __DIR__ . '/_crumbs.php';
}

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
