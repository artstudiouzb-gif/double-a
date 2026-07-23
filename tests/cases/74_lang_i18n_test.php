<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Lang;

// i18n интерфейса сайта: словарь переводов и помощник t()/Lang::t().

test('Словарь UZ загружается и содержит ключевые строки интерфейса', function () {
    $uz = require __DIR__ . '/../../app/Core/lang/uz.php';
    assert_true(is_array($uz), 'uz.php возвращает массив');
    assert_same('Batafsil o‘qish', $uz['Читать далее'] ?? null, 'перевод «Читать далее»');
    assert_same('Barcha yangiliklar', $uz['Все новости'] ?? null, 'перевод «Все новости»');
    assert_true(isset($uz['Проекты и инициативы']), 'есть перевод вводного текста раздела');
    // Ни один перевод не пустой.
    foreach ($uz as $key => $val) {
        assert_true(is_string($val) && $val !== '', 'перевод не пустой: ' . $key);
    }
});

test('Словари UZ и EN синхронны и не содержат пустых переводов', function () {
    $uz = require __DIR__ . '/../../app/Core/lang/uz.php';
    $en = require __DIR__ . '/../../app/Core/lang/en.php';
    assert_same([], array_values(array_diff(array_keys($uz), array_keys($en))), 'ключи UZ присутствуют в EN');
    assert_same([], array_values(array_diff(array_keys($en), array_keys($uz))), 'ключи EN присутствуют в UZ');
    foreach (['uz' => $uz, 'en' => $en] as $lang => $dictionary) {
        foreach ($dictionary as $key => $value) {
            assert_true(is_string($value) && trim($value) !== '', "{$lang}: перевод не пустой: {$key}");
        }
    }
});

test('Публичная шапка переводит навигацию, поиск и панель доступности', function () {
    $header = (string) file_get_contents(APP_ROOT . '/app/Views/site/_header.php');
    foreach (['Для слабовидящих', 'Перейти к содержимому', 'Закрыть поиск'] as $label) {
        assert_contains("\$et('{$label}')", $header, "шапка переводит: {$label}");
    }
    assert_not_contains('>Перейти к содержимому</a>', $header);
    assert_not_contains('>Обычная версия</a>', $header);
});

test('Глобальный помощник t() определён', function () {
    assert_true(function_exists('t'), 'функция t() зарегистрирована в bootstrap');
});

test('Lang::t: перевод, идентичность на языке по умолчанию и фолбэк', function () {
    if (!Database::isConnected()) {
        return; // Language::defaultCode() требует БД
    }
    // Язык по умолчанию (ru) — ключ возвращается как есть.
    assert_same('Читать далее', Lang::t('Читать далее', 'ru'));
    // UZ — из словаря.
    assert_same('Batafsil o‘qish', Lang::t('Читать далее', 'uz'));
    // Неизвестный ключ — возвращается сам ключ (безопасный фолбэк).
    assert_same('__no_such_key__', Lang::t('__no_such_key__', 'uz'));
});
