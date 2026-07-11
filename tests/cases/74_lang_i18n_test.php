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
