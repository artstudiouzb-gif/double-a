<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\News;
use App\Models\SocialPost;

/**
 * Настройки авто-публикации в соцсети и построение полезной нагрузки поста
 * из новости. Токены хранятся в таблице settings в зашифрованном виде
 * (доступ к управлению — только у супер-админа).
 */
final class SocialSettings
{
    /** Обязательные поля конфигурации по сетям. */
    private const REQUIRED = [
        'telegram' => ['token', 'chat_id'],
        'facebook' => ['token', 'page_id'],
        'linkedin' => ['token', 'author'],
        'instagram' => ['token', 'user_id'],
    ];

    /** Ключи настроек по сетям (кроме флага *_enabled). */
    public const FIELDS = [
        'telegram' => ['token', 'chat_id'],
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
     * Полезная нагрузка поста из строки новости. gallery — абсолютные ссылки
     * на дополнительные фото (для Telegram sendMediaGroup).
     * @param array<string,mixed> $news
     * @return array{message:string, link:string, image_url:string, title:string, gallery:list<string>}
     */
    public static function buildPost(array $news): array
    {
        $base = AppUrl::base();
        $link = $base . '/news/' . rawurlencode((string) $news['slug']);
        $abs = static fn (string $u): string => preg_match('#^https?://#', $u) ? $u : $base . '/' . ltrim($u, '/');

        $title = trim((string) ($news['title'] ?? ''));
        $excerpt = trim((string) ($news['excerpt'] ?? ''));
        $message = $excerpt !== '' ? $title . "\n\n" . $excerpt : $title;

        $cover = News::getCoverImage($news) ?? '';
        if ($cover !== '') {
            $cover = $abs($cover);
        }

        $gallery = [];
        if (!empty($news['id'])) {
            foreach (\App\Models\NewsImage::forNews((int) $news['id']) as $img) {
                $gallery[] = $abs((string) $img['path']);
            }
        }

        return ['message' => $message, 'link' => $link, 'image_url' => $cover, 'title' => $title, 'gallery' => $gallery];
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
