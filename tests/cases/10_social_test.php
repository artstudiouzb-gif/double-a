<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\SocialPublisher;
use App\Core\SocialSettings;
use App\Models\SocialPost;

// --- Юнит-тесты адаптеров с поддельным HTTP-транспортом (без реальных сетей) ---

test('Social: Facebook публикует и возвращает remote_id', function () {
    $calls = [];
    $http = function ($method, $url, $body, $headers) use (&$calls) {
        $calls[] = ['method' => $method, 'url' => $url, 'body' => $body];
        return ['status' => 200, 'body' => '{"id":"page_1_2"}', 'error' => ''];
    };
    $pub = new SocialPublisher($http);
    $res = $pub->publish('facebook', ['token' => 'T', 'page_id' => 'PAGE'], ['message' => 'Hi', 'link' => 'https://x/y']);
    assert_true($res['ok']);
    assert_same('page_1_2', $res['remote_id']);
    assert_contains('/PAGE/feed', $calls[0]['url']);
    assert_contains('access_token=T', $calls[0]['body']);
});

test('Social: ошибка Graph возвращает сообщение', function () {
    $http = fn ($m, $u, $b, $h) => ['status' => 400, 'body' => '{"error":{"message":"Invalid token"}}', 'error' => ''];
    $res = (new SocialPublisher($http))->publish('facebook', ['token' => 'T', 'page_id' => 'P'], ['message' => 'x', 'link' => 'https://x']);
    assert_false($res['ok']);
    assert_same('Invalid token', $res['error']);
});

test('Social: LinkedIn шлёт Bearer и ugcPosts', function () {
    $seen = [];
    $http = function ($m, $u, $b, $h) use (&$seen) {
        $seen = ['url' => $u, 'headers' => $h, 'body' => $b];
        return ['status' => 201, 'body' => '{"id":"urn:li:share:99"}', 'error' => ''];
    };
    $res = (new SocialPublisher($http))->publish('linkedin', ['token' => 'LT', 'author' => 'urn:li:organization:5'], ['message' => 'Post', 'link' => 'https://x/a']);
    assert_true($res['ok']);
    assert_same('urn:li:share:99', $res['remote_id']);
    assert_contains('/v2/ugcPosts', $seen['url']);
    assert_true(in_array('Authorization: Bearer LT', $seen['headers'], true));
    assert_contains('urn:li:organization:5', $seen['body']);
});

test('Social: Instagram — двухшаговая публикация', function () {
    $step = 0;
    $http = function ($m, $u, $b, $h) use (&$step) {
        $step++;
        if ($step === 1) { return ['status' => 200, 'body' => '{"id":"CONTAINER"}', 'error' => '']; }
        return ['status' => 200, 'body' => '{"id":"MEDIA_9"}', 'error' => ''];
    };
    $res = (new SocialPublisher($http))->publish('instagram', ['token' => 'T', 'user_id' => 'IG'], ['message' => 'x', 'link' => 'https://x', 'image_url' => 'https://x/i.jpg']);
    assert_same(2, $step, 'должно быть два запроса');
    assert_true($res['ok']);
    assert_same('MEDIA_9', $res['remote_id']);
});

test('Social: Instagram без картинки — ошибка', function () {
    $res = (new SocialPublisher())->publish('instagram', ['token' => 'T', 'user_id' => 'IG'], ['message' => 'x', 'link' => 'https://x', 'image_url' => '']);
    assert_false($res['ok']);
    assert_contains('изображение', $res['error']);
});

test('SocialSettings::buildPost собирает message/link/абсолютный image', function () {
    // app.url задан в bootstrap теста как http://localhost.
    $post = SocialSettings::buildPost(['slug' => 'hello', 'title' => 'Заголовок', 'excerpt' => 'Кратко', 'image' => '/uploads/public/c.jpg']);
    assert_contains('http://localhost/news/hello', $post['link']);
    assert_contains('Заголовок', $post['message']);
    assert_contains('Кратко', $post['message']);
    assert_same('http://localhost/uploads/public/c.jpg', $post['image_url']);
});

// --- Очередь (нужна тестовая БД) ---

test('SocialPost: очередь — enqueue/pending/markSent/markFailed', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $nid = (int) ($pdo->query("INSERT INTO news (title,slug,status) VALUES ('N','soc-" . bin2hex(random_bytes(3)) . "','published')") !== false ? $pdo->lastInsertId() : 0);

    SocialPost::enqueue($nid, 'facebook');
    SocialPost::enqueue($nid, 'facebook'); // повтор не создаёт дубликат
    $rows = SocialPost::forNews($nid);
    assert_same(1, count($rows));

    $batch = SocialPost::pendingBatch(10);
    $mine = array_values(array_filter($batch, fn ($r) => (int) $r['news_id'] === $nid));
    assert_same(1, count($mine));

    SocialPost::markSent((int) $mine[0]['id'], 'REMOTE_1');
    $after = SocialPost::forNews($nid);
    assert_same('sent', $after[0]['status']);
    assert_same('REMOTE_1', $after[0]['remote_id']);
});
