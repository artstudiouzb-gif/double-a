<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Проверка пользовательских URL. Две задачи:
 *
 *  1. isSafeLink()   — URL для вывода в href/src разметки: только http/https,
 *     mailto/tel или относительный путь; javascript:/data: и прочее отсекается.
 *  2. isSafeRemote() — URL, по которому СЕРВЕР будет делать исходящий запрос:
 *     дополнительно резолвит хост и запрещает приватные/loopback/link-local
 *     диапазоны (защита от SSRF).
 *
 * На текущий момент CMS нигде не выполняет серверных запросов по введённому
 * пользователем URL (изображения по ссылке только сохраняются как строка, без
 * скачивания). isSafeRemote() предоставляется как обязательный шлюз на случай
 * появления такой функции (webhooks, импорт по URL, авто-публикация).
 */
final class UrlGuard
{
    /** Ссылка безопасна для вывода в атрибут href/src. */
    public static function isSafeLink(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        // Относительные пути и якоря — безопасны.
        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    /**
     * URL безопасен для серверного исходящего запроса (нет SSRF): схема http/https,
     * хост существует и не указывает на приватный/loopback/link-local адрес.
     */
    public static function isSafeRemote(string $url): bool
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];

        // Резолвим все A/AAAA-записи и проверяем каждую: DNS-rebinding и
        // хитрые имена не должны привести на внутренний адрес.
        $ips = self::resolveHost($host);
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> */
    private static function resolveHost(string $host): array
    {
        // Хост может быть литеральным IP.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        // IPv6 в скобках.
        $trimmed = trim($host, '[]');
        if (filter_var($trimmed, FILTER_VALIDATE_IP)) {
            return [$trimmed];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $r) {
            if (!empty($r['ip'])) {
                $ips[] = $r['ip'];
            }
            if (!empty($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }
        if ($ips === []) {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = $resolved;
            }
        }

        return $ips;
    }

    private static function isPublicIp(string $ip): bool
    {
        // FILTER_FLAG_NO_PRIV_RANGE + NO_RES_RANGE отсекают 10/8, 172.16/12,
        // 192.168/16, 127/8, 169.254/16, ::1, fc00::/7, fe80::/10 и т.п.
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
