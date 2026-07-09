<?php
/** @var array $data */
$title = $data['title'] ?? '';
$items = $data['items'] ?? [];
?>
<div class="block-categories">
    <?php if ($title !== ''): ?><h2 class="block-categories__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
    <?php if (empty($items)): ?>
        <p class="block-categories__empty">Категории ещё не добавлены.</p>
    <?php else: ?>
        <div class="cat-grid">
            <?php foreach ($items as $i => $item): ?>
                <?php
                $url = trim((string) ($item['url'] ?? ''));
                $tag = $url !== '' ? 'a' : 'span';
                $href = $url !== '' ? ' href="' . htmlspecialchars($url, ENT_QUOTES) . '"' : '';
                ?>
                <<?= $tag ?> class="cat-tile<?= $i === 0 ? ' is-active' : '' ?>"<?= $href ?>>
                    <?php if (!empty($item['icon_svg'])): ?>
                        <span class="cat-tile__icon" aria-hidden="true"><?= $item['icon_svg'] ?></span>
                    <?php endif; ?>
                    <span class="cat-tile__label"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES) ?></span>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
