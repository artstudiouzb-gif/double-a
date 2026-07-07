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
<nav class="content-crumbs" aria-label="Хлебные крошки">
    <?php $last = count($crumbs) - 1; ?>
    <?php foreach ($crumbs as $i => $c): ?>
        <?php if (!empty($c['url']) && $i !== $last): ?>
            <a href="<?= htmlspecialchars((string) $c['url'], ENT_QUOTES) ?>"><?= htmlspecialchars((string) $c['label'], ENT_QUOTES) ?></a>
            <span aria-hidden="true">/</span>
        <?php else: ?>
            <span><?= htmlspecialchars((string) $c['label'], ENT_QUOTES) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
