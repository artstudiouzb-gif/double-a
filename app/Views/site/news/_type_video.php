<?php

use App\Core\Video;

/** @var array $news */
$videoId = Video::youtubeId($news['video_url'] ?? null);
?>
<article class="news-single news-single--video">
    <h1><?= htmlspecialchars($news['title'], ENT_QUOTES) ?></h1>
    <time><?= htmlspecialchars(substr((string) $news['published_at'], 0, 10), ENT_QUOTES) ?></time>

    <?php if ($videoId !== null): ?>
        <?php
        // Превью не обращается к YouTube-плееру до клика (задача 66): показываем
        // обложку с иконкой Play; JS подставит iframe по клику. Fallback обложки —
        // hqdefault.jpg (data-fallback).
        $thumb = Video::youtubeThumbnail($videoId);
        $fallback = 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg';
        $embed = Video::youtubeEmbed($videoId) . '&autoplay=1';
        ?>
        <div class="news-video skeleton" data-youtube="<?= htmlspecialchars($videoId, ENT_QUOTES) ?>"
             data-embed="<?= htmlspecialchars($embed, ENT_QUOTES) ?>">
            <img class="news-video__thumb" src="<?= htmlspecialchars($thumb, ENT_QUOTES) ?>"
                 data-fallback="<?= htmlspecialchars($fallback, ENT_QUOTES) ?>"
                 alt="<?= htmlspecialchars((string) $news['title'], ENT_QUOTES) ?>" loading="lazy" decoding="async">
            <button type="button" class="news-video__play" aria-label="Смотреть видео"></button>
        </div>
    <?php endif; ?>

    <div class="news-single__content"><?= $news['content'] ?></div>
</article>
