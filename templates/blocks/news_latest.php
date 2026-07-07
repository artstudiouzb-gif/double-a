<?php
/** @var array $data */
$title = $data['title'] ?? '';
$news = $data['news'] ?? [];
$allUrl = $data['all_url'] ?? '/news';
?>
<div class="block-news">
    <?php if ($title !== ''): ?>
        <div class="block-news__head">
            <h2 class="block-news__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2>
            <a class="block-news__all" href="<?= htmlspecialchars($allUrl, ENT_QUOTES) ?>">Все новости →</a>
        </div>
    <?php endif; ?>
    <?php if (empty($news)): ?>
        <p class="block-news__empty">Новостей пока нет.</p>
    <?php else: ?>
        <div class="block-news__grid">
            <?php foreach ($news as $item): ?>
                <article class="news-card">
                    <a class="news-card__link" href="<?= htmlspecialchars($item['url'], ENT_QUOTES) ?>">
                        <?php if (!empty($item['cover'])): ?>
                            <span class="news-card__cover"><img src="<?= htmlspecialchars($item['cover'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>" loading="lazy"></span>
                        <?php endif; ?>
                        <span class="news-card__body">
                            <?php if (!empty($item['published_at'])): ?>
                                <time class="news-card__date"><?= htmlspecialchars(date('d.m.Y', strtotime((string) $item['published_at'])), ENT_QUOTES) ?></time>
                            <?php endif; ?>
                            <span class="news-card__title"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></span>
                            <?php if (!empty($item['excerpt'])): ?>
                                <span class="news-card__excerpt"><?= htmlspecialchars(mb_substr(strip_tags((string) $item['excerpt']), 0, 140), ENT_QUOTES) ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
