<?php
/** @var array $data */
$title = $data['title'] ?? '';
$allText = trim((string) ($data['all_text'] ?? ''));
$allUrl = trim((string) ($data['all_url'] ?? ''));
$items = $data['items'] ?? [];
?>
<?php $carousel = count($items) > 4; ?>
<div class="block-imgcards" data-carousel>
    <div class="section-head">
        <?php if ($title !== ''): ?><h2 class="section-head__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
        <div class="section-head__tools">
            <?php if ($allText !== '' && $allUrl !== ''): ?><a class="section-head__all" href="<?= htmlspecialchars($allUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($allText, ENT_QUOTES) ?> →</a><?php endif; ?>
            <?php if ($carousel): ?>
                <span class="carousel-nav">
                    <button type="button" class="carousel-nav__btn" data-carousel-prev aria-label="<?= htmlspecialchars(t('Назад'), ENT_QUOTES) ?>">‹</button>
                    <button type="button" class="carousel-nav__btn" data-carousel-next aria-label="Вперёд">›</button>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php if (empty($items)): ?>
        <p class="block-imgcards__empty">Карточки ещё не добавлены.</p>
    <?php else: ?>
        <div class="imgcards-grid<?= $carousel ? ' imgcards-grid--carousel' : '' ?>" data-carousel-track>
            <?php foreach ($items as $item): ?>
                <?php
                $url = trim((string) ($item['url'] ?? ''));
                $img = trim((string) ($item['image'] ?? ''));
                $tag = $url !== '' ? 'a' : 'div';
                ?>
                <<?= $tag ?> class="imgcard"<?= $url !== '' ? ' href="' . htmlspecialchars($url, ENT_QUOTES) . '"' : '' ?>>
                    <span class="imgcard__media"<?= $img !== '' ? ' style="background-image:url(\'' . htmlspecialchars($img, ENT_QUOTES) . '\')"' : '' ?>></span>
                    <span class="imgcard__overlay"></span>
                    <span class="imgcard__body">
                        <span class="imgcard__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                        <?php if ($url !== ''): ?><span class="imgcard__more"><?= htmlspecialchars(t('Подробнее'), ENT_QUOTES) ?> →</span><?php endif; ?>
                    </span>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
