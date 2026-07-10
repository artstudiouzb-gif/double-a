<?php
/** @var array $data */
$title = trim((string) ($data['title'] ?? ''));
$allText = trim((string) ($data['all_text'] ?? ''));
$allUrl = trim((string) ($data['all_url'] ?? ''));
$items = $data['items'] ?? [];
$statusLabels = ['done' => 'Завершён', 'active' => 'В процессе', 'planned' => 'Запланирован'];
?>
<div class="block-stages">
    <div class="section-head">
        <?php if ($title !== ''): ?><h2 class="section-head__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
        <?php if ($allText !== '' && $allUrl !== ''): ?><a class="section-head__all" href="<?= htmlspecialchars($allUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($allText, ENT_QUOTES) ?> →</a><?php endif; ?>
    </div>
    <?php if (empty($items)): ?>
        <p class="block-stages__empty">Этапы ещё не добавлены.</p>
    <?php else: ?>
        <ol class="stages">
            <?php foreach ($items as $item): ?>
                <?php $status = in_array($item['status'] ?? '', ['done', 'active', 'planned'], true) ? $item['status'] : 'planned'; ?>
                <li class="stage stage--<?= $status ?>">
                    <span class="stage__dot"></span>
                    <span class="stage__year"><?= htmlspecialchars((string) ($item['year'] ?? ''), ENT_QUOTES) ?></span>
                    <?php if (!empty($item['stage'])): ?><span class="stage__label"><?= htmlspecialchars((string) $item['stage'], ENT_QUOTES) ?></span><?php endif; ?>
                    <?php if (!empty($item['title'])): ?><span class="stage__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span><?php endif; ?>
                    <?php if (!empty($item['text'])): ?><span class="stage__text"><?= htmlspecialchars((string) $item['text'], ENT_QUOTES) ?></span><?php endif; ?>
                    <span class="stage__status"><?= htmlspecialchars((string) ($item['status_text'] ?? '') !== '' ? (string) $item['status_text'] : $statusLabels[$status], ENT_QUOTES) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
