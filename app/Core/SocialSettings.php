<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Language;
use App\Models\News;
use App\Models\NewsTranslation;
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

    /**
     * Ключи настроек по сетям (кроме флага *_enabled). signature — подпись
     * под постом; в Telegram допускает HTML-разметку (<b>, <a href>), в
     * остальных сетях API принимает только обычный текст (голые URL там
     * становятся кликабельными сами; в Instagram ссылки не кликабельны).
     */
    public const FIELDS = [
        'telegram' => ['token', 'chat_id', 'signature'],
        'facebook' => ['token', 'page_id', 'signature'],
        'linkedin' => ['token', 'author', 'signature'],
        'instagram' => ['token', 'user_id', 'signature'],
    ];

    /** Поля-подписи выводятся в админке многострочным полем. */
    public const TEXTAREA_FIELDS = ['signature'];

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

        // Один бот на всё: если отдельный токен для публикаций не задан, берём
        // основной токен бота из раздела «Telegram». Раньше два независимых
        // поля расходились, и публикация падала с «Not Found» при рабочем входе.
        if ($network === 'telegram' && trim((string) ($cfg['token'] ?? '')) === '') {
            $cfg['token'] = trim((string) \App\Models\Setting::get('telegram_bot_token', ''));
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
        $langs = self::languageBlocks($news, $base);

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

        return [
            'message' => $message,
            'link' => $link,
            'image_url' => $cover,
            'title' => $title,
            'gallery' => $gallery,
            'langs' => $langs,
        ];
    }

    /**
     * Блоки новости по языкам для двуязычного поста: сначала узбекский
     * (гос. язык), затем русский. Язык попадает в список только если у него
     * есть собственный заголовок — localize() при отсутствии перевода
     * возвращает базовую строку, и без этой проверки текст задвоился бы.
     *
     * @param array<string,mixed> $news
     * @return list<array{code:string,label:string,title:string,excerpt:string,link:string,read_more:string}>
     */
    private static function languageBlocks(array $news, string $base): array
    {
        $default = Language::defaultCode();
        $slug = rawurlencode((string) $news['slug']);
        // Метки языков и надпись «читать дальше» на соответствующем языке.
        $meta = [
            'uz' => ['label' => "O‘zbekcha", 'read_more' => "Saytda o‘qish →"],
            'ru' => ['label' => 'Русский', 'read_more' => 'Читать на сайте →'],
            'en' => ['label' => 'English', 'read_more' => 'Read on the site →'],
        ];

        $blocks = [];
        foreach (['uz', 'ru'] as $code) {
            if ($code === $default) {
                $row = $news;
            } else {
                $translation = NewsTranslation::find((int) ($news['id'] ?? 0), $code);
                if ($translation === null || trim((string) ($translation['title'] ?? '')) === '') {
                    continue; // перевода нет — язык пропускаем, дубля не будет
                }
                $row = News::localize($news, $code);
            }
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $blocks[] = [
                'code' => $code,
                'label' => $meta[$code]['label'] ?? strtoupper($code),
                'title' => $title,
                'excerpt' => trim((string) ($row['excerpt'] ?? '')),
                'link' => $base . ($code === $default ? '' : '/' . $code) . '/news/' . $slug,
                'read_more' => $meta[$code]['read_more'] ?? 'Читать →',
            ];
        }

        return $blocks;
    }

    /**
     * Ставит новость в очередь публикации в готовые сети. Если задан $only —
     * только в эту сеть (кнопка конкретной соцсети), иначе во все готовые.
     */
    public static function enqueueForNews(int $newsId, ?string $only = null): int
    {
        $count = 0;
        foreach (self::readyNetworks() as $network) {
            if ($only !== null && $network !== $only) {
                continue;
            }
            SocialPost::enqueue($newsId, $network);
            $count++;
        }

        return $count;
    }

    /**
     * Отправляет одну строку очереди немедленно. Та же логика, что в воркере
     * (app/Console/social_worker.php) — единый источник правды. Обновляет
     * статус строки (sent/failed) и возвращает результат.
     *
     * @param array<string, mixed> $row
     * @return array{ok: bool, error: ?string}
     */
    public static function dispatchRow(array $row, ?SocialPublisher $publisher = null): array
    {
        $id = (int) $row['id'];
        $network = (string) $row['network'];
        $news = News::findById((int) $row['news_id']);

        if ($news === null || ($news['status'] ?? '') !== 'published' || !empty($news['deleted_at'])) {
            $err = 'Новость недоступна или не опубликована.';
            SocialPost::markFailed($id, $err);

            return ['ok' => false, 'error' => $err];
        }
        if (!self::isReady($network)) {
            $err = 'Сеть ' . $network . ' не настроена/выключена.';
            SocialPost::markFailed($id, $err);

            return ['ok' => false, 'error' => $err];
        }

        $publisher ??= new SocialPublisher();
        $result = $publisher->publish($network, self::configFor($network), self::buildPost($news));

        if ($result['ok']) {
            SocialPost::markSent($id, $result['remote_id']);

            return ['ok' => true, 'error' => null];
        }

        SocialPost::markFailed($id, (string) $result['error']);

        return ['ok' => false, 'error' => (string) $result['error']];
    }

    /**
     * Обрабатывает очередь публикаций — та же работа, что воркер по Cron:
     * забирает порцию pending и отправляет. Используется и кнопкой «запустить
     * сейчас» в админке, и CLI-воркером.
     *
     * @return array{sent: int, failed: int, taken: int}
     */
    public static function dispatchQueue(int $limit = 20): array
    {
        $batch = SocialPost::pendingBatch($limit);
        if ($batch === []) {
            return ['sent' => 0, 'failed' => 0, 'taken' => 0];
        }

        $publisher = new SocialPublisher();
        $sent = 0;
        $failed = 0;
        foreach ($batch as $row) {
            $res = self::dispatchRow($row, $publisher);
            if ($res['ok']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'taken' => count($batch)];
    }

    /**
     * Немедленная отправка всех pending-публикаций новости (кнопка «в соцсети»).
     * Что не удалось — остаётся в очереди (status pending) и будет дослано
     * воркером по расписанию.
     *
     * @return array{sent: int, failed: int, errors: array<int, string>}
     */
    public static function dispatchPendingForNews(int $newsId, ?string $only = null): array
    {
        $sent = 0;
        $failed = 0;
        $errors = [];
        $publisher = new SocialPublisher();

        foreach (SocialPost::forNews($newsId) as $row) {
            if ((string) ($row['status'] ?? '') !== 'pending') {
                continue;
            }
            if ($only !== null && (string) ($row['network'] ?? '') !== $only) {
                continue;
            }
            $res = self::dispatchRow($row, $publisher);
            if ($res['ok']) {
                $sent++;
            } else {
                $failed++;
                $errors[] = (string) ($row['network'] ?? '') . ': ' . (string) $res['error'];
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'errors' => $errors];
    }
}
