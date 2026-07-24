<?php
/** @var array $data */
$title = $data['title'] ?? '';
$members = $data['members'] ?? [];
?>
<div class="block-team">
    <?php if ($title !== ''): ?><h2 class="block-team__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
    <?php if (empty($members)): ?>
        <p class="block-team__empty">Раздел команды пока пуст.</p>
    <?php else: ?>
        <div class="block-team__grid">
            <?php foreach ($members as $m): ?>
                <?php
                $mName = (string) ($m['name'] ?? '');
                // Инициалы для аватара-заглушки, когда фото не загружено.
                $mInitials = '';
                foreach (preg_split('/\s+/', trim($mName)) ?: [] as $word) {
                    if ($word !== '') {
                        $mInitials .= mb_substr($word, 0, 1);
                    }
                    if (mb_strlen($mInitials) >= 2) {
                        break;
                    }
                }
                ?>
                <div class="team-card">
                    <?php if (!empty($m['photo'])): ?>
                        <?= \App\Core\Media::picture((string) $m['photo'], $mName, null, null, 'team-card__photo', true, '(max-width: 700px) 100vw, 25vw') ?>
                    <?php elseif ($mInitials !== ''): ?>
                        <span class="team-card__avatar" aria-hidden="true"><?= htmlspecialchars($mInitials, ENT_QUOTES) ?></span>
                    <?php endif; ?>
                    <div class="team-card__name"><?= htmlspecialchars($mName, ENT_QUOTES) ?></div>
                    <?php if (!empty($m['position'])): ?>
                        <div class="team-card__position"><?= htmlspecialchars($m['position'], ENT_QUOTES) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
