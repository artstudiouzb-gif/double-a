<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\EnvironmentCheck;
use App\Core\View;
use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use PDO;

final class InstallController
{
    public static function isInstalled(): bool
    {
        return defined('APP_INSTALLED') && APP_INSTALLED;
    }

    /**
     * Аппаратная блокировка: после установки любой доступ к установщику
     * возвращает 403.
     */
    private function guardInstalled(): void
    {
        if (self::isInstalled()) {
            http_response_code(403);
            exit('Установка уже завершена (403 Forbidden).');
        }
    }

    // --- Шаг 1: проверка окружения ---
    public function step1(): void
    {
        $this->guardInstalled();
        View::render('install/step1', [
            'requirements' => EnvironmentCheck::requirements(),
            'permissions' => EnvironmentCheck::permissions(),
            'allPassed' => EnvironmentCheck::allPassed(),
        ]);
    }

    // --- Шаг 2: параметры БД + генерация конфига ---
    public function step2(): void
    {
        $this->guardInstalled();
        View::render('install/step2', ['error' => null, 'data' => []]);
    }

    public function step2Submit(): void
    {
        $this->guardInstalled();
        Csrf::verifyRequest();

        $data = [
            'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
            'port' => trim((string) ($_POST['db_port'] ?? '3306')),
            'database' => trim((string) ($_POST['db_name'] ?? '')),
            'username' => trim((string) ($_POST['db_user'] ?? '')),
            'password' => (string) ($_POST['db_pass'] ?? ''),
        ];

        if ($data['database'] === '' || $data['username'] === '') {
            View::render('install/step2', ['error' => 'Укажите имя базы данных и пользователя.', 'data' => $data]);
            return;
        }

        try {
            $dbName = str_replace('`', '', $data['database']);
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            try {
                // Сначала подключаемся сразу к указанной базе: на shared-хостинге
                // она уже создана в панели, а глобального права CREATE у
                // пользователя нет — «CREATE DATABASE IF NOT EXISTS» там падает
                // с 1044 даже для существующей базы (MySQL проверяет привилегию
                // раньше, чем IF NOT EXISTS).
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $data['host'], $data['port'], $dbName);
                $pdo = new PDO($dsn, $data['username'], $data['password'], $options);
            } catch (\PDOException $e) {
                if (!str_contains($e->getMessage(), '[1049]') && (int) $e->getCode() !== 1049) {
                    throw $e;
                }
                // Базы не существует — создаём (VPS/свой сервер, где у
                // пользователя есть право CREATE).
                $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $data['host'], $data['port']);
                $pdo = new PDO($dsn, $data['username'], $data['password'], $options);
                $pdo->exec('CREATE DATABASE `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $pdo->exec('USE `' . $dbName . '`');
            }

            // Импортируем схему.
            $schema = file_get_contents(APP_ROOT . '/database/schema.sql');
            if ($schema === false) {
                throw new \RuntimeException('Не найден файл database/schema.sql.');
            }
            $pdo->exec($schema);
        } catch (\Throwable $e) {
            View::render('install/step2', [
                'error' => 'Ошибка подключения/импорта: ' . $e->getMessage() . self::dbErrorHint($e->getMessage()),
                'data' => $data,
            ]);
            return;
        }

        // Генерируем config/config.php.
        $this->writeConfig($data);

        $_SESSION['install_db'] = $data;
        header('Location: /install/step3');
        exit;
    }

    /**
     * Подсказка к типичным ошибкам MySQL на шаге 2, чтобы пользователь
     * панельного хостинга понял, что делать, без чтения документации.
     */
    public static function dbErrorHint(string $message): string
    {
        if (str_contains($message, '1044')) {
            return ' Подсказка: у пользователя нет прав на эту базу. На shared-хостинге'
                . ' создайте базу и пользователя в панели и назначьте пользователя на базу'
                . ' со всеми привилегиями (cPanel: «Базы данных MySQL» → «Добавить'
                . ' пользователя в базу данных»), затем повторите этот шаг.';
        }
        if (str_contains($message, '1045')) {
            return ' Подсказка: неверный логин или пароль пользователя БД. Проверьте их'
                . ' в панели хостинга (пароль можно задать заново).';
        }
        if (str_contains($message, '2002') || stripos($message, 'getaddrinfo') !== false) {
            return ' Подсказка: сервер БД недоступен по указанному хосту/порту.'
                . ' На shared-хостинге обычно нужен хост «localhost».';
        }
        return '';
    }

    // --- Шаг 3: сайт и локаль ---
    public function step3(): void
    {
        $this->guardInstalled();
        $this->requireConfig();
        View::render('install/step3', [
            'error' => null,
            'languages' => Language::all(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function step3Submit(): void
    {
        $this->guardInstalled();
        $this->requireConfig();
        Csrf::verifyRequest();

        $siteName = trim((string) ($_POST['site_name'] ?? ''));
        $description = trim((string) ($_POST['site_description'] ?? ''));
        $timezone = (string) ($_POST['timezone'] ?? 'UTC');
        $defaultLang = (string) ($_POST['default_language'] ?? 'ru');

        if ($siteName === '') {
            View::render('install/step3', [
                'error' => 'Укажите название сайта.',
                'languages' => Language::all(),
                'timezones' => \DateTimeZone::listIdentifiers(),
            ]);
            return;
        }

        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            $timezone = 'UTC';
        }

        Setting::set('site_name', $siteName);
        Setting::set('site_description', $description);

        // Назначаем язык по умолчанию.
        if (Language::isActive($defaultLang)) {
            $pdo = Database::pdo();
            $pdo->exec('UPDATE languages SET is_default = 0');
            $stmt = $pdo->prepare('UPDATE languages SET is_default = 1, is_active = 1 WHERE code = :code');
            $stmt->execute([':code' => $defaultLang]);
        }

        // Сохраняем таймзону в config.php.
        $this->updateConfigTimezone($timezone);

        $_SESSION['install_site'] = true;
        header('Location: /install/step4');
        exit;
    }

    // --- Шаг 4: супер-администратор + финализация ---
    public function step4(): void
    {
        $this->guardInstalled();
        $this->requireConfig();
        View::render('install/step4', ['error' => null, 'data' => []]);
    }

    public function step4Submit(): void
    {
        $this->guardInstalled();
        $this->requireConfig();
        Csrf::verifyRequest();

        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $data = ['username' => $username, 'email' => $email];

        if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
            View::render('install/step4', [
                'error' => 'Заполните все поля. Пароль — не короче 10 символов, email — корректный.',
                'data' => $data,
            ]);
            return;
        }

        if (User::findByUsername($username)) {
            View::render('install/step4', ['error' => 'Пользователь с таким именем уже существует.', 'data' => $data]);
            return;
        }

        // Создаём администратора с максимальной ролью. Подтверждение входа
        // кодом через Telegram включится после указания токена шлюза в
        // настройках и телефона в профиле.
        User::create($username, $email, $password, 'admin');

        // Файл-маркер завершённой установки (блокирует установщик).
        @file_put_contents(APP_ROOT . '/storage/installed.lock', date('c') . PHP_EOL);

        unset($_SESSION['install_db'], $_SESSION['install_site']);

        View::render('install/done', []);
    }

    // ---- helpers ----

    private function requireConfig(): void
    {
        if (!is_file(APP_ROOT . '/config/config.php') || !Database::isConnected()) {
            header('Location: /install/step2');
            exit;
        }
    }

    private function writeConfig(array $db): void
    {
        $appUrl = $this->guessAppUrl();
        $tpl = "<?php\n\ndeclare(strict_types=1);\n\n"
            . "// Сгенерировано веб-инсталлятором ArtStudio CMS.\n"
            . "return [\n"
            . "    'app' => [\n"
            . "        'env' => getenv('APP_ENV') ?: 'production',\n"
            . "        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),\n"
            . "        'url' => getenv('APP_URL') ?: " . var_export($appUrl, true) . ",\n"
            . "        'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',\n"
            . "    ],\n"
            . "    'db' => [\n"
            . "        'host' => getenv('DB_HOST') ?: " . var_export($db['host'], true) . ",\n"
            . "        'port' => getenv('DB_PORT') ?: " . var_export($db['port'], true) . ",\n"
            . "        'database' => getenv('DB_DATABASE') ?: " . var_export($db['database'], true) . ",\n"
            . "        'username' => getenv('DB_USERNAME') ?: " . var_export($db['username'], true) . ",\n"
            . "        'password' => getenv('DB_PASSWORD') ?: " . var_export($db['password'], true) . ",\n"
            . "        'charset' => 'utf8mb4',\n"
            . "    ],\n"
            . "    'session' => ['name' => 'asc_session', 'lifetime' => 7200],\n"
            . "    'security' => [\n"
            . "        'login_max_attempts' => 5,\n"
            . "        'login_lockout_minutes' => 15,\n"
            . "        'login_attempts_window_minutes' => 15,\n"
            . "    ],\n"
            . "    'paths' => [\n"
            . "        'protected_uploads' => __DIR__ . '/../storage/protected_uploads',\n"
            . "        'public_uploads' => __DIR__ . '/../public/uploads/public',\n"
            . "        'public_uploads_url' => '/uploads/public',\n"
            . "    ],\n"
            . "    'mail' => [\n"
            . "        'host' => getenv('SMTP_HOST') ?: '',\n"
            . "        'port' => (int) (getenv('SMTP_PORT') ?: 587),\n"
            . "        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',\n"
            . "        'username' => getenv('SMTP_USERNAME') ?: '',\n"
            . "        'password' => getenv('SMTP_PASSWORD') ?: '',\n"
            . "        'from_email' => getenv('SMTP_FROM_EMAIL') ?: '',\n"
            . "        'from_name' => getenv('SMTP_FROM_NAME') ?: 'ArtStudio CMS',\n"
            . "        'timeout' => 15,\n"
            . "    ],\n"
            . "];\n";

        file_put_contents(APP_ROOT . '/config/config.php', $tpl);
    }

    private function updateConfigTimezone(string $timezone): void
    {
        $file = APP_ROOT . '/config/config.php';
        $content = file_get_contents($file);
        if ($content === false) {
            return;
        }
        $content = preg_replace(
            "/'timezone' => getenv\\('APP_TIMEZONE'\\) \\?: '[^']*'/",
            "'timezone' => getenv('APP_TIMEZONE') ?: " . var_export($timezone, true),
            $content
        );
        file_put_contents($file, $content);
    }

    private function guessAppUrl(): string
    {
        return \App\Core\RequestUrl::origin();
    }
}
