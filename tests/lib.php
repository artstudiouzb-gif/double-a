<?php

declare(strict_types=1);

/*
 * Микро-фреймворк ассертов для нативного тест-раннера. Никаких зависимостей.
 * Использование в файлах tests/cases/*.php:
 *
 *   test('описание', function () {
 *       assert_same(2, 1 + 1);
 *       assert_true(is_string('x'));
 *   });
 */

final class TestRunner
{
    /** @var array<int, array{name: string, fn: callable}> */
    public static array $tests = [];
    public static int $passed = 0;
    public static int $failed = 0;
    public static int $skipped = 0;
    /** @var array<int, string> */
    public static array $failures = [];
}

final class SkipTest extends \RuntimeException
{
}

function test(string $name, callable $fn): void
{
    TestRunner::$tests[] = ['name' => $name, 'fn' => $fn];
}

function skip_test(string $reason): void
{
    throw new SkipTest($reason);
}

function assert_true(bool $cond, string $message = ''): void
{
    if ($cond !== true) {
        throw new \RuntimeException('assert_true failed' . ($message !== '' ? ": {$message}" : ''));
    }
}

function assert_false(bool $cond, string $message = ''): void
{
    if ($cond !== false) {
        throw new \RuntimeException('assert_false failed' . ($message !== '' ? ": {$message}" : ''));
    }
}

/** @param mixed $expected @param mixed $actual */
function assert_same($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new \RuntimeException(sprintf(
            "assert_same failed%s\n     expected: %s\n     actual:   %s",
            $message !== '' ? ": {$message}" : '',
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new \RuntimeException(sprintf(
            "assert_contains failed%s\n     needle:   %s\n     haystack: %s",
            $message !== '' ? ": {$message}" : '',
            $needle,
            mb_strlen($haystack) > 300 ? mb_substr($haystack, 0, 300) . '…' : $haystack
        ));
    }
}

function assert_not_contains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new \RuntimeException(sprintf(
            "assert_not_contains failed%s\n     forbidden needle found: %s",
            $message !== '' ? ": {$message}" : '',
            $needle
        ));
    }
}

function run_tests(): int
{
    foreach (TestRunner::$tests as $t) {
        try {
            ($t['fn'])();
            TestRunner::$passed++;
            fwrite(STDOUT, "  \033[32m✓\033[0m {$t['name']}\n");
        } catch (SkipTest $s) {
            TestRunner::$skipped++;
            fwrite(STDOUT, "  \033[33m•\033[0m {$t['name']} (пропущен: {$s->getMessage()})\n");
        } catch (\Throwable $e) {
            TestRunner::$failed++;
            TestRunner::$failures[] = $t['name'];
            fwrite(STDOUT, "  \033[31m✗\033[0m {$t['name']}\n");
            foreach (explode("\n", (string) $e->getMessage()) as $line) {
                fwrite(STDOUT, "      {$line}\n");
            }
        }
    }

    fwrite(STDOUT, sprintf(
        "\nИтого: %d пройдено, %d провалено, %d пропущено\n",
        TestRunner::$passed,
        TestRunner::$failed,
        TestRunner::$skipped
    ));

    return TestRunner::$failed === 0 ? 0 : 1;
}
