<?php
/** @var array $data */
$title = $data['title'] ?? '';
$allText = trim((string) ($data['all_text'] ?? ''));
$allUrl = trim((string) ($data['all_url'] ?? ''));
$items = $data['items'] ?? [];
?>
<div class="block-persons">
    <div class="section-head">
        <?php if ($title !== ''): ?><h2 class="section-head__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
        <?php if ($allText !== '' && $allUrl !== ''): ?><a class="section-head__all" href="<?= htmlspecialchars($allUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($allText, ENT_QUOTES) ?> →</a><?php endif; ?>
    </div>
    <?php if (empty($items)): ?>
        <p class="block-persons__empty">Карточки ещё не добавлены.</p>
    <?php else: ?>
        <div class="persons-grid">
            <?php foreach ($items as $item): ?>
                <?php
                $photo = trim((string) ($item['photo'] ?? ''));
                $name = trim((string) ($item['name'] ?? ''));
                $url = trim((string) ($item['url'] ?? ''));
                $vacant = $photo === '' && $name === '';
                ?>
                <div class="person-card<?= $vacant ? ' person-card--vacant' : '' ?>">
                    <?php if ($photo !== ''): ?>
                        <span class="person-card__photo" style="background-image:url('<?= htmlspecialchars($photo, ENT_QUOTES) ?>')"></span>
                    <?php else: ?>
                        <span class="person-card__placeholder">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="34" height="34"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
                            <span class="person-card__vacant">Вакантно</span>
                        </span>
                    <?php endif; ?>
                    <span class="person-card__body">
                        <?php if ($name !== ''): ?><span class="person-card__name"><?= htmlspecialchars($name, ENT_QUOTES) ?></span><?php endif; ?>
                        <?php if (!empty($item['role'])): ?><span class="person-card__role"><?= htmlspecialchars((string) $item['role'], ENT_QUOTES) ?></span><?php endif; ?>
                        <?php if ($url !== ''): ?><a class="person-card__more" href="<?= htmlspecialchars($url, ENT_QUOTES) ?>"><?= htmlspecialchars(t('Подробнее'), ENT_QUOTES) ?> →</a><?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
