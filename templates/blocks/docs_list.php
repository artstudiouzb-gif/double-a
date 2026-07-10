<?php
/** @var array $data */
$title = trim((string) ($data['title'] ?? ''));
$allText = trim((string) ($data['all_text'] ?? ''));
$allUrl = trim((string) ($data['all_url'] ?? ''));
$items = $data['items'] ?? [];
$cols = max(1, min(4, (int) ($data['columns'] ?? 4)));
?>
<div class="block-docslist">
    <div class="section-head">
        <?php if ($title !== ''): ?><h2 class="section-head__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
        <?php if ($allText !== '' && $allUrl !== ''): ?><a class="section-head__all" href="<?= htmlspecialchars($allUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($allText, ENT_QUOTES) ?> →</a><?php endif; ?>
    </div>
    <?php if (empty($items)): ?>
        <p class="block-docslist__empty">Документы ещё не добавлены.</p>
    <?php else: ?>
        <div class="docslist-grid" style="--docs-cols:<?= $cols ?>">
            <?php foreach ($items as $doc): ?>
                <?php $url = trim((string) ($doc['url'] ?? '')); $tag = $url !== '' ? 'a' : 'div'; ?>
                <<?= $tag ?> class="doc-card"<?= $url !== '' ? ' href="' . htmlspecialchars($url, ENT_QUOTES) . '" download' : '' ?>>
                    <span class="doc-card__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="24" height="24"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/></svg>
                    </span>
                    <span class="doc-card__body">
                        <span class="doc-card__title"><?= htmlspecialchars((string) ($doc['title'] ?? ''), ENT_QUOTES) ?></span>
                        <?php if (!empty($doc['meta'])): ?><span class="doc-card__meta"><?= htmlspecialchars((string) $doc['meta'], ENT_QUOTES) ?></span><?php endif; ?>
                    </span>
                    <?php if ($url !== ''): ?>
                        <span class="doc-card__dl">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18"><path d="M12 4v11m0 0 4-4m-4 4-4-4"/><path d="M5 19h14"/></svg>
                        </span>
                    <?php endif; ?>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
