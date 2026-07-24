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
        <?php // В режиме карусели полоса прокручивается вбок — делаем её
              // доступной с клавиатуры и подписываем для экранного диктора. ?>
        <div class="imgcards-grid<?= $carousel ? ' imgcards-grid--carousel' : '' ?>" data-carousel-track<?= $carousel ? ' tabindex="0" role="group" aria-label="' . htmlspecialchars(t('Карточки — прокрутка вбок'), ENT_QUOTES) . '"' : '' ?>>
            <?php foreach ($items as $item): ?>
                <?php
                $url = trim((string) ($item['url'] ?? ''));
                $img = trim((string) ($item['image'] ?? ''));
                $tag = $url !== '' ? 'a' : 'div';
                ?>
                <<?= $tag ?> class="imgcard"<?= $url !== '' ? ' href="' . htmlspecialchars($url, ENT_QUOTES) . '"' : '' ?>>
                    <?php if ($img !== ''): ?>
                        <?= \App\Core\Media::picture($img, (string) $item['title'], null, null, 'imgcard__media', true, '(max-width: 700px) 100vw, 25vw') ?>
                    <?php else: ?>
                        <span class="imgcard__media" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="imgcard__overlay"></span>
                    <?php if (!empty($item['metric'])): ?>
                        <span class="imgcard__badge" title="<?= htmlspecialchars((string) ($item['metric_label'] ?? ''), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $item['metric'], ENT_QUOTES) ?></span>
                    <?php endif; ?>
                    <span class="imgcard__body">
                        <span class="imgcard__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                        <?php if ($url !== ''): ?><span class="imgcard__more"><?= htmlspecialchars(t('Подробнее'), ENT_QUOTES) ?> →</span><?php endif; ?>
                    </span>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
