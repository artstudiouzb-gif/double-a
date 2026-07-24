<?php
/** @var array $data */
$title = $data['title'] ?? '';
$items = $data['items'] ?? [];
?>
<div class="block-testimonials">
    <?php if ($title !== ''): ?><h2 class="block-testimonials__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
    <?php // Полоса прокручивается вбок: с клавиатуры до неё нужно добраться
          // и пролистать стрелками, поэтому область фокусируемая и подписана. ?>
    <div class="block-testimonials__track" tabindex="0" role="group"
         aria-label="<?= htmlspecialchars(t('Отзывы — прокрутка вбок'), ENT_QUOTES) ?>">
        <?php foreach ($items as $item): ?>
            <?php
            $tName = (string) ($item['name'] ?? '');
            // Инициалы для аватара-заглушки, когда фото автора не загружено.
            $tInitials = '';
            foreach (preg_split('/\s+/', trim($tName)) ?: [] as $word) {
                if ($word !== '') {
                    $tInitials .= mb_substr($word, 0, 1);
                }
                if (mb_strlen($tInitials) >= 2) {
                    break;
                }
            }
            ?>
            <figure class="testimonial">
                <blockquote class="testimonial__quote"><?= htmlspecialchars($item['quote'] ?? '', ENT_QUOTES) ?></blockquote>
                <figcaption class="testimonial__author">
                    <?php if (!empty($item['photo'])): ?>
                        <?= \App\Core\Media::picture((string) $item['photo'], $tName, null, null, 'testimonial__photo', true, '48px') ?>
                    <?php elseif ($tInitials !== ''): ?>
                        <span class="testimonial__avatar" aria-hidden="true"><?= htmlspecialchars($tInitials, ENT_QUOTES) ?></span>
                    <?php endif; ?>
                    <span class="testimonial__meta">
                        <span class="testimonial__name"><?= htmlspecialchars($tName, ENT_QUOTES) ?></span>
                        <?php if (!empty($item['company'])): ?>
                            <span class="testimonial__company"><?= htmlspecialchars($item['company'], ENT_QUOTES) ?></span>
                        <?php endif; ?>
                    </span>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
</div>
