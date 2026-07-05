<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

// Файловые проверки (без БД): каждая миграция непуста и содержит SQL-DDL/DML.
test('Миграции: все файлы непусты и содержат SQL', function () {
    $files = glob(APP_ROOT . '/database/migrations/*.sql') ?: [];
    assert_true($files !== [], 'нет ни одного файла миграции');
    foreach ($files as $f) {
        $sql = (string) file_get_contents($f);
        assert_true(trim($sql) !== '', basename($f) . ' пуст');
        assert_true(
            (bool) preg_match('/\b(CREATE|ALTER|INSERT|UPDATE|DROP)\b/i', $sql),
            basename($f) . ' не содержит SQL-операторов'
        );
    }
});

test('Миграции: schema.sql перечисляет их как применённые (консистентность)', function () {
    $schema = (string) file_get_contents(APP_ROOT . '/database/schema.sql');
    foreach (glob(APP_ROOT . '/database/migrations/*.sql') ?: [] as $f) {
        $name = basename($f);
        assert_contains($name, $schema, "schema.sql не отмечает {$name} как применённую");
    }
});

// БД-проверка (гейт по окружению): применяем schema.sql к чистой тестовой базе
// и убеждаемся, что миграции не оставляют «новых» (schema их уже содержит).
test('Миграции: применение schema.sql + сверка таблиц (нужна тестовая БД)', function () {
    $db = getenv('TEST_DB_DATABASE');
    if ($db === false || $db === '') {
        skip_test('TEST_DB_* не заданы');
    }

    Database::init([
        'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('TEST_DB_PORT') ?: '3306',
        'database' => $db,
        'username' => getenv('TEST_DB_USERNAME') ?: 'root',
        'password' => getenv('TEST_DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ]);

    $pdo = Database::pdo();
    // Новые таблицы Блока 11 должны существовать (их создаёт schema.sql).
    foreach (['password_resets', 'backup_codes', 'user_sessions', 'migrations'] as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        assert_true($stmt->fetchColumn() !== false, "таблица {$table} отсутствует");
    }

    // migrate.php при загруженной schema.sql не должен находить новых миграций.
    $applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(\PDO::FETCH_COLUMN);
    $applied = array_flip($applied);
    foreach (glob(APP_ROOT . '/database/migrations/*.sql') ?: [] as $f) {
        assert_true(isset($applied[basename($f)]), basename($f) . ' не отмечена применённой');
    }
});
