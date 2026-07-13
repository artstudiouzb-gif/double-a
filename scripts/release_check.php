<?php

declare(strict_types=1);

/**
 * Read-only проверка сервера перед/после выпуска.
 *
 *   php scripts/release_check.php
 *   php scripts/release_check.php --allow-pending  # перед применением миграций
 *   php scripts/release_check.php --json
 */

require __DIR__ . '/../app/Core/Cli.php';
\App\Core\Cli::assertCli();
require __DIR__ . '/../app/Core/Config.php';
require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/../app/Core/SecretBox.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\SecretBox;

$root = dirname(__DIR__);
$jsonOutput = in_array('--json', $argv, true);
$allowPending = in_array('--allow-pending', $argv, true);
$checks = [];

$add = static function (string $name, string $status, string $message) use (&$checks): void {
    $checks[] = ['name' => $name, 'status' => $status, 'message' => $message];
};

$add('php_version', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'error', 'PHP ' . PHP_VERSION);
foreach (['pdo_mysql', 'mbstring', 'gd', 'curl', 'dom', 'fileinfo', 'openssl', 'zip'] as $extension) {
    $add('ext_' . $extension, extension_loaded($extension) ? 'ok' : 'error', $extension);
}

$configFile = $root . '/config/config.php';
$installedLock = $root . '/storage/installed.lock';
$add('config', is_file($configFile) ? 'ok' : 'error', 'config/config.php');
$add('install_lock', is_file($installedLock) ? 'ok' : 'error', 'storage/installed.lock');

foreach (['storage/logs', 'storage/cache', 'storage/backups', 'storage/protected_uploads', 'public/uploads/public'] as $relative) {
    $path = $root . '/' . $relative;
    $add('writable_' . str_replace('/', '_', $relative), is_dir($path) && is_writable($path) ? 'ok' : 'error', $relative);
}

$free = @disk_free_space($root);
if ($free !== false) {
    $add('disk_space', $free >= 256 * 1024 * 1024 ? 'ok' : 'warning', round($free / 1024 / 1024) . ' MiB free');
}

if (is_file($configFile)) {
    try {
        $config = require $configFile;
        if (!is_array($config)) {
            throw new RuntimeException('config.php должен вернуть массив');
        }
        Config::set($config);

        $env = (string) ($config['app']['env'] ?? 'production');
        $debug = (bool) ($config['app']['debug'] ?? false);
        $url = (string) ($config['app']['url'] ?? '');
        $add('production_env', $env === 'production' ? 'ok' : 'warning', 'APP_ENV=' . $env);
        $add('debug_disabled', !$debug ? 'ok' : 'error', $debug ? 'APP_DEBUG включён' : 'APP_DEBUG выключен');
        $add('https_url', str_starts_with($url, 'https://') ? 'ok' : 'error', $url !== '' ? $url : 'APP_URL не задан');
        $add(
            'encryption_key',
            SecretBox::hasValidCurrentKey() ? 'ok' : 'error',
            SecretBox::hasValidCurrentKey() ? 'Ключ шифрования корректен' : 'Задайте APP_ENCRYPTION_KEY: 64 hex-символа'
        );

        Database::init((array) ($config['db'] ?? []));
        Database::pdo()->query('SELECT 1');
        $add('database', 'ok', 'Соединение установлено');

        $applied = [];
        try {
            $applied = Database::pdo()->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            $add('migrations_table', 'error', 'Таблица migrations недоступна');
        }
        $migrationFiles = array_map('basename', glob($root . '/database/migrations/*.sql') ?: []);
        $pending = array_values(array_diff($migrationFiles, $applied));
        $migrationStatus = $pending === [] ? 'ok' : ($allowPending ? 'warning' : 'error');
        $add('migrations', $migrationStatus, $pending === [] ? 'Все применены' : 'Ожидают: ' . implode(', ', $pending));
    } catch (Throwable $e) {
        $add('runtime', 'error', $e->getMessage());
    }
}

$backupFiles = glob($root . '/storage/backups/*.zip') ?: [];
$latestBackup = 0;
foreach ($backupFiles as $backupFile) {
    $latestBackup = max($latestBackup, (int) filemtime($backupFile));
}
if ($latestBackup === 0) {
    $add('recent_backup', 'warning', 'Локальные резервные копии не найдены');
} else {
    $ageHours = (int) floor((time() - $latestBackup) / 3600);
    $add('recent_backup', $ageHours <= 48 ? 'ok' : 'warning', 'Последняя копия: ' . $ageHours . ' ч. назад');
}

$errors = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'error'));
$warnings = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'warning'));

if ($jsonOutput) {
    echo json_encode(['ok' => $errors === 0, 'errors' => $errors, 'warnings' => $warnings, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} else {
    foreach ($checks as $check) {
        $mark = match ($check['status']) {
            'ok' => 'OK',
            'warning' => 'WARN',
            default => 'FAIL',
        };
        fwrite($check['status'] === 'error' ? STDERR : STDOUT, sprintf("[%s] %-28s %s\n", $mark, $check['name'], $check['message']));
    }
    fwrite(STDOUT, sprintf("Итог: ошибок %d, предупреждений %d.%s", $errors, $warnings, PHP_EOL));
}

exit($errors === 0 ? 0 : 1);
