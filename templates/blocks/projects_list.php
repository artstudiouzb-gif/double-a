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
                        <?= \App\Core\Media::picture((string) $p['cover_image'], (string) ($p['title'] ?? ''), null, null, 'project-card__cover', true, '(max-width: 700px) 100vw, 33vw') ?>
                    <?php endif; ?>
                    <?php if (!empty($p['result_metric'])): ?>
                        <div class="project-card__metric">
                            <b><?= htmlspecialchars((string) $p['result_metric'], ENT_QUOTES) ?></b>
                            <?php if (!empty($p['result_label'])): ?><span><?= htmlspecialchars((string) $p['result_label'], ENT_QUOTES) ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="project-card__title"><?= htmlspecialchars($p['title'] ?? '', ENT_QUOTES) ?></div>
                    <?php if (!empty($p['description'])): ?>
                        <p class="project-card__desc"><?= htmlspecialchars(excerpt((string) $p['description'], 160), ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
