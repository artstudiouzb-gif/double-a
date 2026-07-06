<?php
/** @var array $data */
$title = $data['title'] ?? '';
$items = $data['items'] ?? [];
?>
<div class="block-testimonials">
    <?php if ($title !== ''): ?><h2 class="block-testimonials__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
    <div class="block-testimonials__track">
        <?php foreach ($items as $item): ?>
            <figure class="testimonial">
                <?php if (!empty($item['photo'])): ?>
                    <img class="testimonial__photo" src="<?= htmlspecialchars($item['photo'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES) ?>" loading="lazy">
                <?php endif; ?>
                <blockquote class="testimonial__quote"><?= htmlspecialchars($item['quote'] ?? '', ENT_QUOTES) ?></blockquote>
                <figcaption class="testimonial__author">
                    <span class="testimonial__name"><?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES) ?></span>
                    <?php if (!empty($item['company'])): ?>
                        <span class="testimonial__company"><?= htmlspecialchars($item['company'], ENT_QUOTES) ?></span>
                    <?php endif; ?>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
</div>
