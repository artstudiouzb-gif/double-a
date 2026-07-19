<?php

use App\Core\DateFormatter;

/** @var array $data */
$title = $data['title'] ?? '';
$allText = trim((string) ($data['all_text'] ?? ''));
$allUrl = trim((string) ($data['all_url'] ?? ''));
$news = $data['news'] ?? [];

$featured = $news[0] ?? null;
$rest = array_slice($news, 1);
// Первые до 2 из остатка — с миниатюрой, следующие — текстом.
$withThumb = array_slice($rest, 0, 2);
$textOnly = array_slice($rest, 2);

// Дата — единым числовым форматом на всех языках: 19.07.2026.
$fmt = static fn (string $d): string => DateFormatter::short($d);
// Рубрика — только если она реально заполнена: метка, одинаковая у всех
// карточек, ничего не сообщает и лишь спорит с заголовком за внимание.
$badge = static fn (array $i): string => trim((string) ($i['badge'] ?? ''));
?>
<div class="block-newsfeat">
    <div class="section-head">
        <?php if ($title !== ''): ?><h2 class="section-head__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
        <?php if ($allText !== '' && $allUrl !== ''): ?><a class="section-head__all" href="<?= htmlspecialchars($allUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($allText, ENT_QUOTES) ?> →</a><?php endif; ?>
    </div>

    <?php if ($featured === null): ?>
        <p class="block-newsfeat__empty">Новостей пока нет.</p>
    <?php else: ?>
    <div class="newsfeat-grid">
        <a class="newsfeat-lead" href="<?= htmlspecialchars((string) $featured['url'], ENT_QUOTES) ?>">
            <span class="newsfeat-lead__frame">
                <?php if (!empty($featured['cover'])): ?>
                    <img class="newsfeat-lead__media" src="<?= htmlspecialchars((string) $featured['cover'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $featured['title'], ENT_QUOTES) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <span class="newsfeat-lead__media newsfeat-lead__media--empty" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="newsfeat-lead__over">
                    <span class="news-meta">
                        <?php if ($badge($featured) !== ''): ?><span class="news-badge news-badge--on-media"><?= htmlspecialchars($badge($featured), ENT_QUOTES) ?></span><?php endif; ?>
                        <?php if (!empty($featured['published_at'])): ?><time class="newsfeat__date newsfeat__date--on-media"><?= htmlspecialchars($fmt((string) $featured['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                    </span>
                    <span class="newsfeat-lead__title"><?= htmlspecialchars((string) $featured['title'], ENT_QUOTES) ?></span>
                    <?php if (!empty($featured['excerpt'])): ?><span class="newsfeat-lead__excerpt"><?= htmlspecialchars(excerpt((string) $featured['excerpt'], 160), ENT_QUOTES) ?></span><?php endif; ?>
                </span>
            </span>
        </a>

        <div class="newsfeat-side">
            <?php if (!empty($withThumb)): ?>
                <div class="newsfeat-side__thumbs">
                    <?php foreach ($withThumb as $item): ?>
                        <a class="newsfeat-mini" href="<?= htmlspecialchars((string) $item['url'], ENT_QUOTES) ?>">
                            <?php if (!empty($item['cover'])): ?>
                                <img class="newsfeat-mini__media" src="<?= htmlspecialchars((string) $item['cover'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?>" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="newsfeat-mini__media newsfeat-mini__media--empty" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="newsfeat-mini__body">
                                <span class="news-meta">
                                    <?php if ($badge($item) !== ''): ?><span class="news-badge"><?= htmlspecialchars($badge($item), ENT_QUOTES) ?></span><?php endif; ?>
                                    <?php if (!empty($item['published_at'])): ?><time class="newsfeat__date"><?= htmlspecialchars($fmt((string) $item['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                                </span>
                                <span class="newsfeat-mini__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($textOnly)): ?>
                <div class="newsfeat-side__texts">
                    <?php foreach ($textOnly as $item): ?>
                        <a class="newsfeat-text" href="<?= htmlspecialchars((string) $item['url'], ENT_QUOTES) ?>">
                            <span class="news-meta">
                                <?php if ($badge($item) !== ''): ?><span class="news-badge"><?= htmlspecialchars($badge($item), ENT_QUOTES) ?></span><?php endif; ?>
                                <?php if (!empty($item['published_at'])): ?><time class="newsfeat__date"><?= htmlspecialchars($fmt((string) $item['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                            </span>
                            <span class="newsfeat-text__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
