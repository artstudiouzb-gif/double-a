<?php
/** @var array $data */
$items = $data['items'] ?? [];
?>
<?php if (!empty($items)): ?>
<nav class="block-anchornav" aria-label="Разделы страницы">
    <?php foreach ($items as $i => $item): ?>
        <a class="block-anchornav__link<?= $i === 0 ? ' is-active' : '' ?>" href="<?= htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES) ?>"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES) ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
