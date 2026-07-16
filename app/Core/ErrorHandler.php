<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Централизованный перехватчик ошибок и исключений. В production логирует
 * стек в storage/logs/error.log и отдаёт заглушку errors/500.php с HTTP 500,
 * не раскрывая деталей. В режиме отладки показывает подробности.
 */
final class ErrorHandler
{
    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        // Превращаем ошибку в исключение, чтобы обработать единообразно.
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(Throwable $e): void
    {
        $concise = get_class($e) . ': ' . $e->getMessage();
        // Полный стек — в файл; в Telegram уходит компактное сообщение + контекст.
        Logger::log('error', $concise . ' in ' . $e->getFile() . ':' . $e->getLine()
            . PHP_EOL . 'Stack trace:' . PHP_EOL . $e->getTraceAsString(), 'ERROR');
        // Журнал ошибок в панели (понятное объяснение + 7 дней хранения).
        if (defined('APP_INSTALLED') && APP_INSTALLED) {
            \App\Models\ErrorLog::record('ERROR', $concise, $e->getFile(), $e->getLine());
        }
        \App\Core\TelegramNotifier::send('ERROR', $concise, [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $_SERVER['REQUEST_URI'] ?? 'cli',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        self::renderErrorPage($e);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        Logger::critical('Fatal: ' . $error['message'], [
            'file' => $error['file'],
            'line' => $error['line'],
            'url' => $_SERVER['REQUEST_URI'] ?? 'cli',
        ]);
        if (defined('APP_INSTALLED') && APP_INSTALLED) {
            \App\Models\ErrorLog::record('CRITICAL', $error['message'], (string) $error['file'], (int) $error['line']);
        }

        self::renderErrorPage(new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }

    private static function renderErrorPage(Throwable $e): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, (string) $e . PHP_EOL);
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        // Очищаем незавершённый вывод, чтобы отдать чистую страницу ошибки.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (self::$debug) {
            echo '<pre style="padding:20px;font:14px monospace;color:#c00;">';
            echo htmlspecialchars((string) $e, ENT_QUOTES);
            echo '</pre>';
            return;
        }

        $view = dirname(__DIR__) . '/Views/errors/500.php';
        if (is_file($view)) {
            require $view;
        } else {
            echo 'Внутренняя ошибка сервера.';
        }
    }
}
