<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\WebhookDispatcher;
use App\Models\Webhook;
use App\Models\WebhookDelivery;

test('Webhook: HMAC-подпись стабильна и в формате sha256=', function () {
    $sig = WebhookDispatcher::sign('{"a":1}', 'secret');
    assert_true(str_starts_with($sig, 'sha256='));
    assert_same('sha256=' . hash_hmac('sha256', '{"a":1}', 'secret'), $sig);
});

test('Webhook: deliver подписывает и трактует 2xx как успех', function () {
    $seen = [];
    $http = function ($url, $body, $headers) use (&$seen) {
        $seen = ['url' => $url, 'body' => $body, 'headers' => $headers];
        return ['status' => 200, 'body' => 'ok', 'error' => ''];
    };
    $res = WebhookDispatcher::deliver(
        ['payload_json' => '{"event":"x"}'],
        ['url' => 'https://93.184.216.34/hook', 'secret' => 'S'],
        $http
    );
    assert_true($res['ok']);
    assert_same(200, $res['code']);
    $hasSig = false;
    foreach ($seen['headers'] as $h) {
        if (str_starts_with($h, WebhookDispatcher::SIGNATURE_HEADER . ':')) { $hasSig = true; }
    }
    assert_true($hasSig, 'подпись должна быть в заголовках');
});

test('Webhook: deliver трактует 5xx как ошибку', function () {
    $http = fn ($u, $b, $h) => ['status' => 500, 'body' => 'err', 'error' => ''];
    $res = WebhookDispatcher::deliver(['payload_json' => '{}'], ['url' => 'https://93.184.216.34/x', 'secret' => null], $http);
    assert_false($res['ok']);
    assert_same(500, $res['code']);
});

test('Webhook: SSRF — приватный/loopback URL блокируется на доставке', function () {
    $called = false;
    $http = function ($u, $b, $h) use (&$called) { $called = true; return ['status' => 200, 'body' => '', 'error' => '']; };
    $res = WebhookDispatcher::deliver(['payload_json' => '{}'], ['url' => 'http://127.0.0.1/x', 'secret' => null], $http);
    assert_false($res['ok']);
    assert_false($called, 'запрос на loopback не должен выполняться');
});

test('Webhook: dispatch ставит доставки только активным подписчикам события (БД)', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $pdo->exec('DELETE FROM webhook_deliveries');
    $pdo->exec('DELETE FROM webhooks');

    $encryptedHookId = Webhook::create('form.submitted', 'https://93.184.216.34/a', 's1', true);
    Webhook::create('form.submitted', 'https://93.184.216.34/b', null, false); // выключен
    Webhook::create('news.published', 'https://93.184.216.34/c', null, true);  // другое событие

    $rawSecret = (string) $pdo->query('SELECT secret FROM webhooks WHERE id = ' . $encryptedHookId)->fetchColumn();
    assert_true(str_starts_with($rawSecret, 'enc:v1:'), 'секрет webhook зашифрован в БД');
    assert_same('s1', Webhook::findById($encryptedHookId)['secret']);

    $n = WebhookDispatcher::dispatch('form.submitted', ['form' => 'contact']);
    assert_same(1, $n, 'только один активный подписчик form.submitted');

    $pending = WebhookDelivery::pendingBatch(10);
    assert_same(1, count($pending));
    assert_same('form.submitted', $pending[0]['event_type']);
    assert_contains('contact', (string) $pending[0]['payload_json']);
});
