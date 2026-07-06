<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Публикация новости в соцсети через их официальные API нативным HTTP-клиентом
 * (без сторонних библиотек). Поддержаны Facebook (Page feed), LinkedIn
 * (organization/person share) и Instagram (Graph, двухшаговая публикация).
 *
 * HTTP-транспорт инжектируется (callable), что делает адаптеры тестируемыми
 * без реальных запросов к сетям.
 *
 * @phpstan-type Post array{message:string, link:string, image_url?:string}
 * @phpstan-type Result array{ok:bool, remote_id:?string, error:?string}
 */
final class SocialPublisher
{
    public const NETWORKS = ['facebook', 'linkedin', 'instagram'];
    private const GRAPH = 'https://graph.facebook.com/v19.0';

    /** @var callable(string,string,string,array):array */
    private $http;

    /** @param callable|null $http fn(method,url,body,headers):array{status,body,error} */
    public function __construct(?callable $http = null)
    {
        $this->http = $http ?? static fn (string $m, string $u, string $b, array $h) => Http::request($m, $u, $b, $h);
    }

    /**
     * @param array<string,string> $cfg
     * @param array{message:string, link:string, image_url?:string} $post
     * @return array{ok:bool, remote_id:?string, error:?string}
     */
    public function publish(string $network, array $cfg, array $post): array
    {
        return match ($network) {
            'facebook' => $this->facebook($cfg, $post),
            'linkedin' => $this->linkedin($cfg, $post),
            'instagram' => $this->instagram($cfg, $post),
            default => ['ok' => false, 'remote_id' => null, 'error' => 'Неизвестная сеть: ' . $network],
        };
    }

    private function facebook(array $cfg, array $post): array
    {
        if (empty($cfg['token']) || empty($cfg['page_id'])) {
            return self::err('Не заданы токен или ID страницы Facebook.');
        }
        $url = self::GRAPH . '/' . rawurlencode($cfg['page_id']) . '/feed';
        $body = http_build_query([
            'message' => trim($post['message'] . "\n\n" . $post['link']),
            'link' => $post['link'],
            'access_token' => $cfg['token'],
        ]);
        $res = ($this->http)('POST', $url, $body, ['Content-Type: application/x-www-form-urlencoded']);

        return $this->interpretGraph($res);
    }

    private function linkedin(array $cfg, array $post): array
    {
        if (empty($cfg['token']) || empty($cfg['author'])) {
            return self::err('Не заданы токен или автор (URN) LinkedIn.');
        }
        $payload = [
            'author' => $cfg['author'],
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $post['message']],
                    'shareMediaCategory' => 'ARTICLE',
                    'media' => [[
                        'status' => 'READY',
                        'originalUrl' => $post['link'],
                    ]],
                ],
            ],
            'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
        ];
        $res = ($this->http)(
            'POST',
            'https://api.linkedin.com/v2/ugcPosts',
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
            [
                'Authorization: Bearer ' . $cfg['token'],
                'X-Restli-Protocol-Version: 2.0.0',
                'Content-Type: application/json',
            ]
        );

        $data = json_decode($res['body'] ?? '', true);
        if (($res['status'] === 200 || $res['status'] === 201) && !empty($data['id'])) {
            return ['ok' => true, 'remote_id' => (string) $data['id'], 'error' => null];
        }

        return self::err(self::extractError($res, $data));
    }

    private function instagram(array $cfg, array $post): array
    {
        if (empty($cfg['token']) || empty($cfg['user_id'])) {
            return self::err('Не заданы токен или IG user ID.');
        }
        if (empty($post['image_url']) || !preg_match('#^https?://#', (string) $post['image_url'])) {
            return self::err('Для Instagram нужна публичная ссылка на изображение (обложка новости).');
        }

        // Шаг 1: создаём медиа-контейнер.
        $createUrl = self::GRAPH . '/' . rawurlencode($cfg['user_id']) . '/media';
        $createBody = http_build_query([
            'image_url' => $post['image_url'],
            'caption' => trim($post['message'] . "\n\n" . $post['link']),
            'access_token' => $cfg['token'],
        ]);
        $c = ($this->http)('POST', $createUrl, $createBody, ['Content-Type: application/x-www-form-urlencoded']);
        $cData = json_decode($c['body'] ?? '', true);
        if (empty($cData['id'])) {
            return self::err('IG: не удалось создать контейнер. ' . self::extractError($c, $cData));
        }

        // Шаг 2: публикуем контейнер.
        $pubUrl = self::GRAPH . '/' . rawurlencode($cfg['user_id']) . '/media_publish';
        $pubBody = http_build_query([
            'creation_id' => (string) $cData['id'],
            'access_token' => $cfg['token'],
        ]);
        $p = ($this->http)('POST', $pubUrl, $pubBody, ['Content-Type: application/x-www-form-urlencoded']);

        return $this->interpretGraph($p);
    }

    /** Общий разбор ответа Graph API (Facebook/Instagram publish). */
    private function interpretGraph(array $res): array
    {
        $data = json_decode($res['body'] ?? '', true);
        if (($res['status'] === 200 || $res['status'] === 201) && !empty($data['id'])) {
            return ['ok' => true, 'remote_id' => (string) $data['id'], 'error' => null];
        }

        return self::err(self::extractError($res, $data));
    }

    private static function extractError(array $res, mixed $data): string
    {
        if (is_array($data) && isset($data['error']['message'])) {
            return (string) $data['error']['message'];
        }
        if (!empty($res['error'])) {
            return (string) $res['error'];
        }

        return 'HTTP ' . (int) ($res['status'] ?? 0);
    }

    private static function err(string $message): array
    {
        return ['ok' => false, 'remote_id' => null, 'error' => $message];
    }
}
