<?php

declare(strict_types=1);

namespace App\Core;

/** Безопасное определение внешней схемы/host за reverse proxy. */
final class RequestUrl
{
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        // Reverse proxy может передать цепочку значений; внешняя схема — первая.
        $forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        return $forwarded === 'https';
    }

    public static function origin(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:]+\])(?::[0-9]{1,5})?$/i', $host)) {
            $host = 'localhost';
        }

        return (self::isHttps() ? 'https' : 'http') . '://' . $host;
    }
}
