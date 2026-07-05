<?php
/** @var array $data */
/** @var int $blockId */
$slides = $data['slides'] ?? [];
?>
<div class="block-slider" data-block-id="<?= (int) $blockId ?>">
    <div class="block-slider__track">
        <?php foreach ($slides as $index => $slide): ?>
            <div class="block-slider__slide<?= $index === 0 ? ' is-active' : '' ?>">
                <?php if (!empty($slide['image'])): ?>
                    <img src="<?= htmlspecialchars($slide['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($slide['alt'] ?? '', ENT_QUOTES) ?>" loading="lazy">
                <?php endif; ?>
                <?php if (!empty($slide['caption'])): ?>
                    <div class="block-slider__caption"><?= htmlspecialchars($slide['caption'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (count($slides) > 1): ?>
        <div class="block-slider__nav">
            <button type="button" class="block-slider__prev" aria-label="Предыдущий слайд">&#10094;</button>
            <button type="button" class="block-slider__next" aria-label="Следующий слайд">&#10095;</button>
        </div>
    <?php endif; ?>
</div>
