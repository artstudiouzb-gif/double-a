<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\SocialPublisher;
use App\Models\SocialPost;

test('Telegram: ссылка стоит внутри своего языкового блока, а не в общем хвосте', function () {
    $seen = [];
    $http = function ($m, $u, $b, $h) use (&$seen) {
        $seen = json_decode($b, true);
        return ['status' => 200, 'body' => '{"ok":true,"result":{"message_id":5}}'];
    };
    $post = [
        'message' => '', 'link' => 'https://site.uz/news/x',
        'langs' => [
            ['title' => 'Sarlavha', 'excerpt' => 'Qisqacha matn.', 'link' => 'https://site.uz/uz/news/x', 'read_more' => 'Saytda o‘qish →'],
            ['title' => 'Заголовок', 'excerpt' => 'Краткий текст.', 'link' => 'https://site.uz/news/x', 'read_more' => 'Читать на сайте →'],
        ],
    ];
    $sig = '🌐 <a href="https://site.uz">Сайт</a>';
    $res = (new SocialPublisher($http))->publish('telegram', ['token' => 'T', 'chat_id' => '@c', 'signature' => $sig], $post);

    assert_true($res['ok']);
    $text = (string) $seen['text'];

    $uzLink = mb_strpos($text, 'https://site.uz/uz/news/x');
    $sepPos = mb_strpos($text, '———');
    $ruTitle = mb_strpos($text, 'Заголовок');
    $ruLink = mb_strpos($text, 'Читать на сайте');
    $sigPos = mb_strpos($text, '🌐');

    // Узбекская ссылка — до разделителя, русская — после своего заголовка.
    assert_true($uzLink < $sepPos, 'ссылка узбекской версии стоит в узбекском блоке');
    assert_true($sepPos < $ruTitle, 'разделитель перед русским блоком');
    assert_true($ruTitle < $ruLink, 'ссылка русской версии — после русского заголовка');
    // Подпись остаётся общим хвостом в самом конце.
    assert_true($ruLink < $sigPos, 'подпись — последней');
});

test('Telegram: одиночный язык не ломается (ссылка и подпись на месте)', function () {
    $seen = [];
    $http = function ($m, $u, $b, $h) use (&$seen) {
        $seen = json_decode($b, true);
        return ['status' => 200, 'body' => '{"ok":true,"result":{"message_id":5}}'];
    };
    (new SocialPublisher($http))->publish(
        'telegram',
        ['token' => 'T', 'chat_id' => '@c', 'signature' => 'подпись'],
        ['message' => 'Текст новости', 'link' => 'https://site.uz/news/x', 'title' => 'Заголовок']
    );

    $text = (string) $seen['text'];
    assert_contains('Заголовок', $text);
    assert_contains('https://site.uz/news/x', $text);
    assert_not_contains('———', $text, 'разделителя между блоками нет — язык один');
    assert_true(mb_strpos($text, 'https://site.uz/news/x') < mb_strpos($text, 'подпись'));
});

test('Telegram: длинный двуязычный текст с ссылками влезает в лимит 1024', function () {
    $seen = [];
    $http = function ($m, $u, $b, $h) use (&$seen) {
        $seen = json_decode($b, true);
        return ['status' => 200, 'body' => '{"ok":true,"result":[{"message_id":5}]}'];
    };
    $post = [
        'message' => '', 'link' => 'https://site.uz/news/x',
        'image_url' => 'https://site.uz/cover.jpg',
        'langs' => [
            ['title' => 'Sarlavha', 'excerpt' => str_repeat('u', 3000), 'link' => 'https://site.uz/uz/news/x', 'read_more' => 'Saytda o‘qish →'],
            ['title' => 'Заголовок', 'excerpt' => str_repeat('р', 3000), 'link' => 'https://site.uz/news/x', 'read_more' => 'Читать на сайте →'],
        ],
    ];
    (new SocialPublisher($http))->publish('telegram', ['token' => 'T', 'chat_id' => '@c', 'signature' => 'подпись'], $post);

    // Лимит подписи к фото — 1024 символа видимого текста.
    $visible = mb_strlen(strip_tags((string) $seen['caption']));
    assert_true($visible <= 1024, "подпись обязана влезать в лимит, сейчас {$visible}");
    // Обе ссылки уцелели: режется только текст анонсов.
    assert_contains('Saytda o‘qish', (string) $seen['caption']);
    assert_contains('Читать на сайте', (string) $seen['caption']);
});

test('Повторная публикация: кнопка в админке отправляет заново, автопубликация — нет (БД)', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $pdo->exec("INSERT INTO news (title, slug, status, created_at) VALUES ('Repost', 'repost-" . bin2hex(random_bytes(3)) . "', 'published', NOW())");
    $newsId = (int) $pdo->lastInsertId();

    $status = static function (int $newsId) use ($pdo): array {
        $st = $pdo->prepare('SELECT status, attempts FROM social_posts WHERE news_id = :n AND network = :net');
        $st->execute([':n' => $newsId, ':net' => 'telegram']);
        return $st->fetch() ?: [];
    };

    SocialPost::enqueue($newsId, 'telegram');
    assert_same('pending', $status($newsId)['status']);

    // Отправлено — обычная постановка в очередь больше не трогает запись,
    // иначе правка новости плодила бы посты в канале.
    $pdo->exec("UPDATE social_posts SET status = 'sent', sent_at = NOW(), attempts = 1 WHERE news_id = {$newsId}");
    SocialPost::enqueue($newsId, 'telegram');
    assert_same('sent', $status($newsId)['status'], 'автопубликация не переотправляет');

    // Явное «опубликовать заново» из админки возвращает запись в очередь.
    SocialPost::enqueue($newsId, 'telegram', true);
    $row = $status($newsId);
    assert_same('pending', $row['status'], 'кнопка публикации отправляет повторно');
    assert_same(0, (int) $row['attempts'], 'счётчик попыток сбрасывается');

    $pdo->exec("DELETE FROM social_posts WHERE news_id = {$newsId}");
    $pdo->exec("DELETE FROM news WHERE id = {$newsId}");
});

test('Кнопка публикации в админке вызывает enqueueForNews с force', function () {
    $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/NewsController.php');
    assert_contains("enqueueForNews((int) \$news['id'], \$only, true)", $src);
    // А автопубликация при сохранении новости — без force.
    assert_contains('enqueueForNews($id);', $src);
});
