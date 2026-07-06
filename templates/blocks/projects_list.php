<?php
/** @var array $data */
$title = $data['title'] ?? '';
$projects = $data['projects'] ?? [];
?>
<div class="block-projects">
    <?php if ($title !== ''): ?><h2 class="block-projects__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
    <?php if (empty($projects)): ?>
        <p class="block-projects__empty">Проекты пока не добавлены.</p>
    <?php else: ?>
        <div class="block-projects__grid">
            <?php foreach ($projects as $p): ?>
                <div class="project-card">
                    <?php if (!empty($p['cover_image'])): ?>
                        <img class="project-card__cover" src="<?= htmlspecialchars($p['cover_image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($p['title'] ?? '', ENT_QUOTES) ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="project-card__title"><?= htmlspecialchars($p['title'] ?? '', ENT_QUOTES) ?></div>
                    <?php if (!empty($p['description'])): ?>
                        <p class="project-card__desc"><?= htmlspecialchars(mb_substr(strip_tags((string) $p['description']), 0, 160), ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
