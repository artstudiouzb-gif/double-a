<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Сборка текста email-дайджеста новостей (чистые функции; отправку выполняет
 * app/Console/digest_worker.php через очередь писем).
 */
final class Digest
{
    /**
     * Тема письма: «Дайджест новостей — Сайт (12.07.2026)».
     */
    public static function buildSubject(string $siteName, string $date): string
    {
        $siteName = trim($siteName) !== '' ? trim($siteName) : 'Сайт';

        return 'Дайджест новостей — ' . $siteName . ' (' . $date . ')';
    }

    /**
     * Тело письма (plain text): список новостей со ссылками.
     *
     * @param array<int, array<string, mixed>> $items новости (title, slug, excerpt)
     */
    public static function buildBody(array $items, string $siteName, string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $lines = ['Новости за неделю' . (trim($siteName) !== '' ? ' — ' . trim($siteName) : '') . ':', ''];

        foreach ($items as $item) {
            $lines[] = '• ' . trim((string) $item['title']);
            $excerpt = trim((string) ($item['excerpt'] ?? ''));
            if ($excerpt !== '') {
                if (mb_strlen($excerpt) > 200) {
                    $excerpt = mb_substr($excerpt, 0, 200) . '…';
                }
                $lines[] = '  ' . $excerpt;
            }
            $lines[] = '  ' . $baseUrl . '/news/' . trim((string) $item['slug']);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /** Подвал с персональной ссылкой отписки. */
    public static function buildFooter(string $baseUrl, string $token): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        return "—\nВы получили это письмо, потому что подписались на дайджест.\n"
            . 'Отписаться: ' . $baseUrl . '/unsubscribe?token=' . $token;
    }
}
