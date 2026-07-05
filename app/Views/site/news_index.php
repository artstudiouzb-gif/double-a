<?php

use App\Core\AssetCollector;
use App\Core\Locale;
use App\Core\Media;
use App\Models\News;
use App\Core\Video;

/** @var array $items */

$metaTitle = 'Новости';
$metaDescription = '';
AssetCollector::requireJs('news'); // скелетоны + fallback обложек
require __DIR__ . '/_header.php';
?>
<div class="news-list">
    <h1>Новости</h1>
    <?php if (empty($items)): ?>
        <p>Пока нет опубликованных новостей.</p>
    <?php endif; ?>
    <?php foreach ($items as $item): ?>
        <?php
        $url = Locale::url('news/' . $item['slug']);
        $cover = News::getCoverImage($item);
        $isVideo = ($item['layout_type'] ?? 'standard') === 'video' && Video::isYoutube($item['video_url'] ?? null);
        ?>
        <article class="news-list__item">
            <?php if ($cover !== null): ?>
                <a class="news-list__cover<?= $isVideo ? ' news-list__cover--video' : '' ?> skeleton" href="<?= htmlspecialchars($url, ENT_QUOTES) ?>">
                    <?= Media::picture($cover, (string) $item['title'], $item['focal_x'] ?? null, $item['focal_y'] ?? null, 'news-list__img') ?>
                    <?php if ($isVideo): ?><span class="news-list__play" aria-hidden="true"></span><?php endif; ?>
                </a>
            <?php endif; ?>
            <div class="news-list__body">
                <time><?= htmlspecialchars(substr((string) $item['published_at'], 0, 10), ENT_QUOTES) ?></time>
                <h2><a href="<?= htmlspecialchars($url, ENT_QUOTES) ?>"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></a></h2>
                <?php if (!empty($item['excerpt'])): ?><p><?= htmlspecialchars($item['excerpt'], ENT_QUOTES) ?></p><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
