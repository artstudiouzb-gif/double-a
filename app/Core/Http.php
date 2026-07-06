<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Минимальный HTTP-клиент на нативном cURL (расширение ext-curl, не Composer)
 * с fallback на потоковый контекст. Используется для запросов к API соцсетей.
 * TLS-проверка сертификата включена жёстко.
 *
 * @phpstan-type HttpResponse array{status:int, body:string, error:string}
 */
final class Http
{
    /**
     * @param array<int, string> $headers строки заголовков вида "Name: value"
     * @return array{status:int, body:string, error:string}
     */
    public static function request(string $method, string $url, string $body = '', array $headers = [], int $timeout = 20): array
    {
        if (function_exists('curl_init')) {
            return self::viaCurl($method, $url, $body, $headers, $timeout);
        }

        return self::viaStream($method, $url, $body, $headers, $timeout);
    }

    /** @param array<int, string> $headers */
    public static function postForm(string $url, array $fields, array $headers = [], int $timeout = 20): array
    {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        return self::request('POST', $url, http_build_query($fields), $headers, $timeout);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $headers
     */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 20): array
    {
        $headers[] = 'Content-Type: application/json';
        return self::request('POST', $url, (string) json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, $timeout);
    }

    private static function viaCurl(string $method, string $url, string $body, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== '' || $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = $response === false ? (string) curl_error($ch) : '';
        curl_close($ch);

        return [
            'status' => $status,
            'body' => is_string($response) ? $response : '',
            'error' => $error,
        ];
    }

    private static function viaStream(string $method, string $url, string $body, array $headers, int $timeout): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $result = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return [
            'status' => $status,
            'body' => is_string($result) ? $result : '',
            'error' => $result === false ? 'stream request failed' : '',
        ];
    }
}
