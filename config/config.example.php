<?php

declare(strict_types=1);

/*
 * Скопируйте этот файл в config.php и заполните реальными данными,
 * либо задайте те же значения через переменные окружения хостинга
 * (APP_ENV, APP_DEBUG, APP_URL, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD и т.д.)
 */

return [
    'app' => [
        'env' => getenv('APP_ENV') ?: 'production',
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'url' => getenv('APP_URL') ?: 'https://example.com',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Europe/Moscow',
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'artstudio_cms',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => 'asc_session',
        'lifetime' => 7200,
    ],
    'security' => [
        'login_max_attempts' => 5,
        'login_lockout_minutes' => 15,
        'login_attempts_window_minutes' => 15,
    ],
    'paths' => [
        'protected_uploads' => __DIR__ . '/../storage/protected_uploads',
        'public_uploads' => __DIR__ . '/../public/uploads/public',
        'public_uploads_url' => '/uploads/public',
    ],
];
