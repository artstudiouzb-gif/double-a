<?php
/**
 * Хлебные крошки. Ожидает $crumbs = [['label'=>..,'url'=>..], ..]; у последнего
 * элемента url можно опустить (текущая страница). Разметка совместима с
 * .content-crumbs (frontend.css).
 * @var array $crumbs
 */
$crumbs = $crumbs ?? [];
if (count($crumbs) < 2) {
    return;
}
?>
<nav class="content-crumbs<?= !empty($crumbsClass) ? ' ' . htmlspecialchars((string) $crumbsClass, ENT_QUOTES) : '' ?>" aria-label="<?= htmlspecialchars(t('Хлебные крошки'), ENT_QUOTES) ?>">
    <?php $last = count($crumbs) - 1; ?>
    <?php foreach ($crumbs as $i => $c): ?>
        <?php if (!empty($c['url']) && $i !== $last): ?>
            <a href="<?= htmlspecialchars((string) $c['url'], ENT_QUOTES) ?>"><?= htmlspecialchars((string) $c['label'], ENT_QUOTES) ?></a>
            <span aria-hidden="true">/</span>
        <?php else: ?>
            <span><?= htmlspecialchars((string) $c['label'], ENT_QUOTES) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
    <button type="button" class="crumbs-print-btn no-print" data-print-page aria-label="<?= htmlspecialchars(t('Распечатать страницу'), ENT_QUOTES) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2m-12 0v4h8v-4m-8 0h8"/></svg>
        <span><?= htmlspecialchars(t('Печать'), ENT_QUOTES) ?></span>
    </button>
</nav>
