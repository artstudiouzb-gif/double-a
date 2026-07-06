<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\News;
use App\Models\SocialPost;

/**
 * Настройки авто-публикации в соцсети и построение полезной нагрузки поста
 * из новости. Токены хранятся в таблице settings (доступ только у супер-админа).
 */
final class SocialSettings
{
    /** Обязательные поля конфигурации по сетям. */
    private const REQUIRED = [
        'facebook' => ['token', 'page_id'],
        'linkedin' => ['token', 'author'],
        'instagram' => ['token', 'user_id'],
    ];

    /** Ключи настроек по сетям (кроме флага *_enabled). */
    public const FIELDS = [
        'facebook' => ['token', 'page_id'],
        'linkedin' => ['token', 'author'],
        'instagram' => ['token', 'user_id'],
    ];

    public static function isEnabled(string $network): bool
    {
        return \App\Models\Setting::get('social_' . $network . '_enabled', '0') === '1';
    }

    /** @return array<string,string> */
    public static function configFor(string $network): array
    {
        $cfg = [];
        foreach (self::FIELDS[$network] ?? [] as $field) {
            $cfg[$field] = \App\Models\Setting::get('social_' . $network . '_' . $field, '');
        }

        return $cfg;
    }

    /** Сеть включена и все обязательные поля заполнены. */
    public static function isReady(string $network): bool
    {
        if (!self::isEnabled($network)) {
            return false;
        }
        $cfg = self::configFor($network);
        foreach (self::REQUIRED[$network] ?? [] as $field) {
            if (trim((string) ($cfg[$field] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /** @return array<int,string> сети, готовые к публикации */
    public static function readyNetworks(): array
    {
        return array_values(array_filter(SocialPublisher::NETWORKS, [self::class, 'isReady']));
    }

    /**
     * Полезная нагрузка поста из строки новости.
     * @param array<string,mixed> $news
     * @return array{message:string, link:string, image_url:string}
     */
    public static function buildPost(array $news): array
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $link = $base . '/news/' . rawurlencode((string) $news['slug']);

        $title = trim((string) ($news['title'] ?? ''));
        $excerpt = trim((string) ($news['excerpt'] ?? ''));
        $message = $excerpt !== '' ? $title . "\n\n" . $excerpt : $title;

        $cover = News::getCoverImage($news) ?? '';
        if ($cover !== '' && !preg_match('#^https?://#', $cover)) {
            $cover = $base . '/' . ltrim($cover, '/');
        }

        return ['message' => $message, 'link' => $link, 'image_url' => $cover];
    }

    /** Ставит новость в очередь публикации во все готовые сети. */
    public static function enqueueForNews(int $newsId): int
    {
        $count = 0;
        foreach (self::readyNetworks() as $network) {
            SocialPost::enqueue($newsId, $network);
            $count++;
        }

        return $count;
    }
}
