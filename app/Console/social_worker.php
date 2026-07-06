<?php

declare(strict_types=1);

/*
 * Воркер очереди авто-публикаций в соцсети ArtStudio CMS.
 *   php app/Console/social_worker.php
 *
 * Запускать по Cron (например, раз в 5 минут — «пятая звёздочка/5»):
 *   [мин/5] * * * * php /path/to/app/Console/social_worker.php >> /path/to/storage/logs/social_worker.log 2>&1
 *
 * Забирает pending-публикации из social_posts и публикует их через API
 * соответствующей сети (App\Core\SocialPublisher). При ошибке увеличивает
 * счётчик попыток; после трёх неудач публикация помечается как failed.
 */

if (PHP_SAPI !== 'cli') {
    exit('Только из командной строки.');
}

require __DIR__ . '/../Core/bootstrap.php';

use App\Core\Logger;
use App\Core\SocialPublisher;
use App\Core\SocialSettings;
use App\Models\News;
use App\Models\SocialPost;

$batch = SocialPost::pendingBatch(20);
if ($batch === []) {
    fwrite(STDOUT, 'Очередь публикаций пуста.' . PHP_EOL);
    exit(0);
}

$publisher = new SocialPublisher();
$sent = 0;
$failed = 0;

foreach ($batch as $row) {
    $id = (int) $row['id'];
    $network = (string) $row['network'];
    $news = News::findById((int) $row['news_id']);

    // Новость удалена или снята с публикации — не публикуем.
    if ($news === null || ($news['status'] ?? '') !== 'published' || !empty($news['deleted_at'])) {
        SocialPost::markFailed($id, 'Новость недоступна или не опубликована.');
        $failed++;
        continue;
    }
    if (!SocialSettings::isReady($network)) {
        SocialPost::markFailed($id, 'Сеть ' . $network . ' не настроена/выключена.');
        $failed++;
        continue;
    }

    $result = $publisher->publish($network, SocialSettings::configFor($network), SocialSettings::buildPost($news));

    if ($result['ok']) {
        SocialPost::markSent($id, $result['remote_id']);
        $sent++;
        fwrite(STDOUT, sprintf("OK %s <- новость #%d (%s)\n", $network, (int) $row['news_id'], (string) $result['remote_id']));
    } else {
        SocialPost::markFailed($id, (string) $result['error']);
        $failed++;
        Logger::error(sprintf('Social publish failed [%s] news #%d: %s', $network, (int) $row['news_id'], (string) $result['error']));
    }
}

fwrite(STDOUT, sprintf('Готово: опубликовано %d, ошибок %d.%s', $sent, $failed, PHP_EOL));
exit(0);
