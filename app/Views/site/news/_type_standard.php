<?php

use App\Core\Media;

/** @var array $news */
$cover = trim((string) ($news['image'] ?? ''));
?>
<article class="news-single news-single--standard">
    <h1><?= htmlspecialchars($news['title'], ENT_QUOTES) ?></h1>
    <time><?= htmlspecialchars(substr((string) $news['published_at'], 0, 10), ENT_QUOTES) ?></time>
    <?php if ($cover !== ''): ?>
        <div class="news-single__cover">
            <?= Media::picture($cover, (string) $news['title'], $news['focal_x'] ?? null, $news['focal_y'] ?? null, 'news-single__cover-img', false) ?>
        </div>
    <?php endif; ?>
    <div class="news-single__content"><?= $news['content'] ?></div>
</article>
