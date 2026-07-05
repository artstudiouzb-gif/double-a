<?php

use App\Core\Media;

/** @var array $news */
$cover = trim((string) ($news['image'] ?? ''));
?>
<article class="news-single news-single--side">
    <h1><?= htmlspecialchars($news['title'], ENT_QUOTES) ?></h1>
    <time><?= htmlspecialchars(substr((string) $news['published_at'], 0, 10), ENT_QUOTES) ?></time>
    <div class="news-side">
        <?php if ($cover !== ''): ?>
            <aside class="news-side__media">
                <?= Media::picture($cover, (string) $news['title'], $news['focal_x'] ?? null, $news['focal_y'] ?? null, 'news-side__img', false) ?>
            </aside>
        <?php endif; ?>
        <div class="news-side__content news-single__content"><?= $news['content'] ?></div>
    </div>
</article>
