<?php

declare(strict_types=1);

use App\Core\AppUrl;
use App\Core\RequestUrl;

test('Внешний HTTPS корректно определяется за reverse proxy', function () {
    $server = $_SERVER;

    try {
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https, http';
        $_SERVER['HTTP_HOST'] = 'ASR.ArtStudio.UZ';

        assert_true(RequestUrl::isHttps());
        assert_same('https://asr.artstudio.uz', RequestUrl::origin());
    } finally {
        $_SERVER = $server;
    }
});

test('Некорректный Host не попадает в сгенерированные URL', function () {
    $server = $_SERVER;

    try {
        $_SERVER['HTTP_HOST'] = "asr.artstudio.uz\r\nX-Injected: yes";
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

        assert_same('http://localhost', RequestUrl::origin());
    } finally {
        $_SERVER = $server;
    }
});

test('Канонический URL повышает http до https без смены настроенного host', function () {
    assert_same(
        'https://asr.artstudio.uz/cms',
        AppUrl::normalize('http://asr.artstudio.uz/cms/', true)
    );
    assert_same(
        'http://asr.artstudio.uz',
        AppUrl::normalize('http://asr.artstudio.uz/', false)
    );
});

test('Публичные SEO и машинные ответы используют единый канонический URL', function () {
    $files = [
        '/app/Views/site/_header.php',
        '/app/Views/site/_footer.php',
        '/app/Views/site/content_show.php',
        '/app/Views/site/news_show.php',
        '/app/Controllers/Site/NewsController.php',
        '/app/Controllers/Site/OpenDataController.php',
        '/app/Controllers/Site/SitemapController.php',
        '/app/Core/SocialSettings.php',
        '/app/Controllers/Admin/PasswordResetController.php',
        '/app/Controllers/Admin/NewsController.php',
        '/app/Console/push_worker.php',
        '/app/Console/digest_worker.php',
    ];

    foreach ($files as $file) {
        $source = (string) file_get_contents(APP_ROOT . $file);
        assert_contains('AppUrl::base()', $source, $file);
    }
});
