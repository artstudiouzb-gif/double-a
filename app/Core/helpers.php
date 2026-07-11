<?php

declare(strict_types=1);

use App\Core\Lang;

if (!function_exists('t')) {
    /**
     * Короткий помощник перевода интерфейса для шаблонов.
     * Возвращает перевод строки на текущий язык (или сам ключ на языке
     * по умолчанию / при отсутствии перевода). См. App\Core\Lang.
     */
    function t(string $key, ?string $lang = null): string
    {
        return Lang::t($key, $lang);
    }
}
