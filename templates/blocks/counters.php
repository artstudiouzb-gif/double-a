<?php
/** @var array $data */
$title = $data['title'] ?? '';
$items = $data['items'] ?? [];
?>
<div class="block-counters">
    <?php if ($title !== ''): ?><h2 class="block-counters__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
    <div class="block-counters__grid">
        <?php foreach ($items as $item):
            $value = (int) ($item['value'] ?? 0);
        ?>
            <div class="counter">
                <div class="counter__value" data-counter-target="<?= $value ?>"><?= $value ?></div>
                <?php if (!empty($item['suffix'])): ?>
                    <span class="counter__suffix"><?= htmlspecialchars($item['suffix'], ENT_QUOTES) ?></span>
                <?php endif; ?>
                <div class="counter__label"><?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
