<?php
/** @var array $data */
$title = $data['title'] ?? '';
$images = $data['images'] ?? [];
?>
<div class="block-gallery">
    <?php if ($title !== ''): ?><h2><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
    <div class="block-gallery__grid">
        <?php foreach ($images as $image): ?>
            <a class="block-gallery__item" href="<?= htmlspecialchars($image['url'] ?? '#', ENT_QUOTES) ?>" target="_blank" rel="noopener">
                <img src="<?= htmlspecialchars($image['url'] ?? '', ENT_QUOTES) ?>" alt="<?= htmlspecialchars($image['caption'] ?? '', ENT_QUOTES) ?>" loading="lazy">
            </a>
        <?php endforeach; ?>
    </div>
</div>
