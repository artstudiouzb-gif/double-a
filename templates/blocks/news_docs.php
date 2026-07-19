<?php

use App\Core\DateFormatter;

/** @var array $data */
$news = $data['news'] ?? [];
$docs = $data['docs'] ?? [];
// Дата — единым числовым форматом на всех языках: 19.07.2026.
$fmt = static fn (string $d): string => DateFormatter::short($d);
?>
<div class="block-newsdocs">
    <div class="newsdocs-col">
        <div class="section-head">
            <?php if (!empty($data['news_title'])): ?><h2 class="section-head__title"><?= htmlspecialchars((string) $data['news_title'], ENT_QUOTES) ?></h2><?php endif; ?>
            <?php if (!empty($data['news_all_text']) && !empty($data['news_all_url'])): ?><a class="section-head__all" href="<?= htmlspecialchars((string) $data['news_all_url'], ENT_QUOTES) ?>"><?= htmlspecialchars((string) $data['news_all_text'], ENT_QUOTES) ?> →</a><?php endif; ?>
        </div>
        <?php if (empty($news)): ?>
            <p class="block-newsdocs__empty">Новостей пока нет.</p>
        <?php else: ?>
            <div class="newsdocs-news">
                <?php foreach ($news as $item): ?>
                    <a class="newsdocs-item" href="<?= htmlspecialchars((string) $item['url'], ENT_QUOTES) ?>">
                        <?php if (!empty($item['cover'])): ?>
                            <img class="newsdocs-item__media" src="<?= htmlspecialchars((string) $item['cover'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="newsdocs-item__media newsdocs-item__media--empty" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="newsdocs-item__body">
                            <?php if (!empty($item['published_at'])): ?><time class="newsdocs-item__date"><?= htmlspecialchars($fmt((string) $item['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                            <span class="newsdocs-item__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                        </span>
                        <span class="newsdocs-item__arrow">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="newsdocs-col">
        <div class="section-head">
            <?php if (!empty($data['docs_title'])): ?><h2 class="section-head__title"><?= htmlspecialchars((string) $data['docs_title'], ENT_QUOTES) ?></h2><?php endif; ?>
            <?php if (!empty($data['docs_all_text']) && !empty($data['docs_all_url'])): ?><a class="section-head__all" href="<?= htmlspecialchars((string) $data['docs_all_url'], ENT_QUOTES) ?>"><?= htmlspecialchars((string) $data['docs_all_text'], ENT_QUOTES) ?> →</a><?php endif; ?>
        </div>
        <?php if (empty($docs)): ?>
            <p class="block-newsdocs__empty">Документы ещё не добавлены.</p>
        <?php else: ?>
            <div class="newsdocs-docs">
                <?php foreach ($docs as $doc): ?>
                    <?php $url = trim((string) ($doc['url'] ?? '')); $tag = $url !== '' ? 'a' : 'div'; ?>
                    <<?= $tag ?> class="doc-card"<?= $url !== '' ? ' href="' . htmlspecialchars($url, ENT_QUOTES) . '"' : '' ?>>
                        <span class="doc-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="24" height="24"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/></svg>
                        </span>
                        <span class="doc-card__body">
                            <span class="doc-card__title"><?= htmlspecialchars((string) $doc['title'], ENT_QUOTES) ?></span>
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
</div>
