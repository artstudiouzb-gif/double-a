<?php

declare(strict_types=1);

/*
 * Еженедельный email-дайджест новостей.
 *   php app/Console/digest_worker.php
 *
 * Cron (раз в неделю, например понедельник 09:00):
 *   0 9 * * 1 php /path/to/app/Console/digest_worker.php >> /path/to/storage/logs/digest_worker.log 2>&1
 *
 * Собирает новости, опубликованные за последние 7 дней, и ставит письмо в
 * очередь (mail_worker отправляет) каждому подписчику с персональной ссылкой
 * отписки. Нет новых новостей или подписчиков — тихо выходит.
 */

require __DIR__ . '/../Core/Cli.php';
\App\Core\Cli::assertCli();

require __DIR__ . '/../Core/bootstrap.php';

use App\Core\Config;
use App\Core\Digest;
use App\Core\Logger;
use App\Core\ProcessLock;
use App\Models\MailQueue;
use App\Models\News;
use App\Models\Setting;
use App\Models\Subscriber;

$lock = ProcessLock::acquire('digest_worker');
if ($lock === null) {
    fwrite(STDERR, 'digest_worker уже выполняется — пропуск запуска.' . PHP_EOL);
    exit(0);
}

try {
    if (!\App\Core\Mailer::isConfigured()) {
        fwrite(STDOUT, 'SMTP не настроен — дайджест пропущен.' . PHP_EOL);
        exit(0);
    }

    // Новости за последние 7 дней (свежие сверху).
    $weekAgo = date('Y-m-d H:i:s', time() - 7 * 86400);
    $items = array_values(array_filter(
        News::published(50),
        static fn (array $n): bool => (string) ($n['published_at'] ?? '') >= $weekAgo
    ));
    if ($items === []) {
        fwrite(STDOUT, 'За неделю новостей нет — дайджест не отправляется.' . PHP_EOL);
        exit(0);
    }

    $subscribers = Subscriber::all();
    if ($subscribers === []) {
        fwrite(STDOUT, 'Подписчиков нет — дайджест не отправляется.' . PHP_EOL);
        exit(0);
    }

    $siteName = Setting::get('site_name', '');
    $baseUrl = rtrim((string) Config::get('app.url', ''), '/');
    $subject = Digest::buildSubject($siteName, date('d.m.Y'));
    $body = Digest::buildBody($items, $siteName, $baseUrl);

    $queued = 0;
    foreach ($subscribers as $sub) {
        MailQueue::enqueue(
            (string) $sub['email'],
            $subject,
            $body . "\n" . Digest::buildFooter($baseUrl, (string) $sub['token'])
        );
        $queued++;
    }

    Logger::info('Дайджест поставлен в очередь', ['news' => count($items), 'recipients' => $queued]);
    fwrite(STDOUT, sprintf('OK: %d новостей, %d получателей поставлено в очередь.%s', count($items), $queued, PHP_EOL));
    exit(0);
} catch (\Throwable $e) {
    Logger::warning('Дайджест: ошибка', ['error' => $e->getMessage()]);
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    ProcessLock::release($lock);
}
