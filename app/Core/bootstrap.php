<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Глобальные помощники шаблонов (t() — перевод интерфейса).
require __DIR__ . '/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\SecurityHeaders;

define('APP_ROOT', dirname(__DIR__, 2));

// Заголовки безопасности выставляем до любого вывода, чтобы они попали
// на ВСЕ ответы, включая брендированный fail-safe 503 ниже и страницы ошибок.
SecurityHeaders::send();

$configFile = APP_ROOT . '/config/config.php';
$installedLock = APP_ROOT . '/storage/installed.lock';

// Система считается установленной, когда есть и config.php, и файл-маркер.
define('APP_INSTALLED', is_file($configFile) && is_file($installedLock));

ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

if (is_file($configFile)) {
    $config = require $configFile;
    Config::set($config);
    date_default_timezone_set($config['app']['timezone'] ?? 'UTC');
    ErrorHandler::register((bool) ($config['app']['debug'] ?? false));

    if (APP_INSTALLED) {
        // Рабочий режим: недоступность БД -> брендированный 503 (fail-safe),
        // без вывода системного трейса.
        try {
            Database::init($config['db']);
        } catch (\Throwable $e) {
            \App\Core\Logger::critical('Падение БД (503): ' . $e->getMessage(), [
                'url' => $_SERVER['REQUEST_URI'] ?? 'cli',
            ]);
            if (PHP_SAPI !== 'cli') {
                http_response_code(503);
                header('Retry-After: 60');
                $view = APP_ROOT . '/app/Views/errors/503.php';
                echo is_file($view) ? file_get_contents($view) : 'Сервис временно недоступен.';
                exit;
            }
            throw $e;
        }
    } else {
        // Установка ещё не завершена, но config.php уже есть (шаг после
        // генерации конфига): подключаемся к БД мягко, без фатала.
        try {
            Database::init($config['db']);
        } catch (\Throwable $e) {
            // БД ещё может быть недоступна — установщик покажет ошибку сам.
        }
    }
} else {
    // Режим установки: config.php ещё нет. Работаем на минимальных дефолтах.
    Config::set(['app' => ['env' => 'production', 'debug' => true, 'url' => '', 'timezone' => 'UTC']]);
    date_default_timezone_set('UTC');
    ErrorHandler::register(true);
}

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $sessionName = (string) Config::get('session.name', 'asc_session');
    $sessionLifetime = (int) Config::get('session.lifetime', 7200);

    session_name($sessionName);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (!empty($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $sessionLifetime) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}
