<?php

declare(strict_types=1);

use App\Core\PublicResponseCache;

test('Публичные GET и HEAD без сессии можно кешировать', function () {
    assert_true(PublicResponseCache::isCacheableRequest('/', 'GET', false, 200));
    assert_true(PublicResponseCache::isCacheableRequest('/uz/news', 'HEAD', false, 200));
});

test('Сессии, авторизация, ошибки и изменяющие запросы не кешируются', function () {
    assert_false(PublicResponseCache::isCacheableRequest('/', 'GET', true, 200));
    assert_false(PublicResponseCache::isCacheableRequest('/', 'GET', false, 200, true));
    assert_false(PublicResponseCache::isCacheableRequest('/', 'POST', false, 200));
    assert_false(PublicResponseCache::isCacheableRequest('/missing', 'GET', false, 404));
});

test('Чувствительные публичные и служебные маршруты исключены из кеша', function () {
    foreach (['/admin', '/repo/login', '/search', '/uz/search', '/captcha.png', '/push/key', '/unsubscribe', '/health'] as $path) {
        assert_false(PublicResponseCache::isCacheableRequest($path, 'GET', false, 200), $path);
    }
});

test('Изменения публичного контента регистрируют общий сброс кеша', function () {
    $source = (string) file_get_contents(APP_ROOT . '/app/Core/PublicResponseCache.php');
    assert_contains("'/admin/news'", $source);
    assert_contains("'/admin/pages'", $source);
    assert_contains("'/admin/menu'", $source);
    assert_contains("Cache::forgetPrefix('page:')", $source);
});

test('Языковые страницы разделяются в кеше по cookie посетителя', function () {
    $source = (string) file_get_contents(APP_ROOT . '/app/Core/PublicResponseCache.php');
    assert_contains("header('Vary: Accept-Encoding, Cookie')", $source);
});
