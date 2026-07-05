<?php

declare(strict_types=1);

/*
 * Нативный тест-раннер ArtStudio CMS (без Composer/PHPUnit — по требованию
 * «никаких сторонних библиотек/Composer»).
 *
 *   php tests/run.php
 *
 * Часть тестов требует БД (миграции, ACL на реальных данных). Они выполняются
 * только если задан доступ к тестовой базе через переменные окружения:
 *   TEST_DB_DATABASE, TEST_DB_USERNAME, TEST_DB_PASSWORD, TEST_DB_HOST.
 * Иначе такие тесты помечаются как пропущенные, а unit-тесты идут всегда.
 */

if (PHP_SAPI !== 'cli') {
    exit('Только из командной строки.');
}

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lib.php';

$cases = glob(__DIR__ . '/cases/*.php') ?: [];
sort($cases, SORT_STRING);
foreach ($cases as $case) {
    fwrite(STDOUT, "\n\033[1m" . basename($case) . "\033[0m\n");
    require $case;
    // Каждый файл добавляет свои test(...) в очередь; выполняем после сбора.
}

exit(run_tests());
