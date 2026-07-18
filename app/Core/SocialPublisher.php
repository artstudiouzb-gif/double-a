<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Публикация новости в соцсети через их официальные API нативным HTTP-клиентом
 * (без сторонних библиотек). Поддержаны Facebook (Page feed), LinkedIn
 * (organization/person share), Instagram (Graph, двухшаговая публикация) и
 * Telegram-канал (Bot API: sendMessage / sendPhoto / sendMediaGroup для
 * галерей).
 *
 * HTTP-транспорт инжектируется (callable), что делает адаптеры тестируемыми
 * без реальных запросов к сетям.
 *
 * @phpstan-type Post array{message:string, link:string, image_url?:string, title?:string, gallery?:list<string>}
 * @phpstan-type Result array{ok:bool, remote_id:?string, error:?string}
 */
final class SocialPublisher
{
    public const NETWORKS = ['telegram', 'facebook', 'linkedin', 'instagram'];
    private const GRAPH = 'https://graph.facebook.com/v19.0';
    private const TG_API = 'https://api.telegram.org';

    /** Лимиты Telegram: подпись к медиа — 1024 символа, сообщение — 4096. */
    private const TG_CAPTION_LIMIT = 1024;
    private const TG_TEXT_LIMIT = 4096;

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
            'telegram' => $this->telegram($cfg, $post),
            'facebook' => $this->facebook($cfg, $post),
            'linkedin' => $this->linkedin($cfg, $post),
            'instagram' => $this->instagram($cfg, $post),
            default => ['ok' => false, 'remote_id' => null, 'error' => 'Неизвестная сеть: ' . $network],
        };
    }

    /**
     * Telegram-канал: галерея — sendMediaGroup (до 10 фото, подпись у первого),
     * одно фото — sendPhoto, без фото — sendMessage. Подпись: жирный заголовок,
     * анонс и ссылка «Читать на сайте» (HTML-разметка).
     */
    private function telegram(array $cfg, array $post): array
    {
        if (empty($cfg['token']) || empty($cfg['chat_id'])) {
            return self::err('Не заданы токен бота или chat_id канала Telegram.');
        }

        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Публичные https-изображения: обложка + галерея, максимум 10 (лимит API).
        $photos = [];
        foreach (array_merge(
            !empty($post['image_url']) ? [(string) $post['image_url']] : [],
            array_map('strval', (array) ($post['gallery'] ?? []))
        ) as $url) {
            if (preg_match('#^https://#', $url) && !in_array($url, $photos, true)) {
                $photos[] = $url;
            }
        }
        $photos = array_slice($photos, 0, 10);

        $limit = $photos !== [] ? self::TG_CAPTION_LIMIT : self::TG_TEXT_LIMIT;
        $caption = self::telegramCaption($post, (string) ($cfg['signature'] ?? ''), $limit, $esc);

        // Токен используется в URL как есть: rawurlencode ломал бы двоеточие
        // (12345:AAH… → 12345%3AAAH…), и Bot API отвечал бы 404 «Not Found».
        // Токен — доверенная настройка суперадмина; лишь срезаем случайные
        // пробелы/переводы строк от копипаста.
        $api = self::TG_API . '/bot' . trim((string) $cfg['token']);
        $headers = ['Content-Type: application/json'];

        if (count($photos) >= 2) {
            $media = [];
            foreach ($photos as $i => $url) {
                $item = ['type' => 'photo', 'media' => $url];
                if ($i === 0) {
                    $item['caption'] = $caption;
                    $item['parse_mode'] = 'HTML';
                }
                $media[] = $item;
            }
            $payload = ['chat_id' => $cfg['chat_id'], 'media' => $media];
            $res = ($this->http)('POST', $api . '/sendMediaGroup', (string) json_encode($payload, JSON_UNESCAPED_UNICODE), $headers);

            return $this->interpretTelegram($res, true);
        }

        if (count($photos) === 1) {
            $payload = [
                'chat_id' => $cfg['chat_id'],
                'photo' => $photos[0],
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ];
            $res = ($this->http)('POST', $api . '/sendPhoto', (string) json_encode($payload, JSON_UNESCAPED_UNICODE), $headers);

            return $this->interpretTelegram($res);
        }

        $payload = [
            'chat_id' => $cfg['chat_id'],
            'text' => $caption,
            'parse_mode' => 'HTML',
        ];
        $res = ($this->http)('POST', $api . '/sendMessage', (string) json_encode($payload, JSON_UNESCAPED_UNICODE), $headers);

        return $this->interpretTelegram($res);
    }

    /**
     * Проверка подключения к Telegram без публикации: токен (getMe), канал
     * (getChat) и права бота в нём (getChatMember). Диагностика по шагам —
     * иначе единственное «Not Found» в журнале ничего не объясняет.
     *
     * @param array<string,string> $cfg
     * @return array{ok:bool, steps:list<array{name:string, ok:bool, text:string}>}
     */
    public function checkTelegram(array $cfg): array
    {
        $token = trim((string) ($cfg['token'] ?? ''));
        $chatId = trim((string) ($cfg['chat_id'] ?? ''));
        $steps = [];

        if ($token === '' || $chatId === '') {
            $steps[] = ['name' => 'Настройки', 'ok' => false, 'text' => 'Не заполнены токен бота или chat_id канала.'];

            return ['ok' => false, 'steps' => $steps];
        }

        $api = self::TG_API . '/bot' . $token;
        // Транспортную ошибку (нет сети, закрытый исходящий доступ на хостинге)
        // важно не смешивать с ответом API: иначе «нет интернета» выглядело бы
        // как «неверный токен», и чинили бы не то.
        $call = function (string $method, array $payload = []) use ($api): array {
            $res = ($this->http)(
                'POST',
                $api . '/' . $method,
                (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
                ['Content-Type: application/json']
            );
            $data = json_decode((string) ($res['body'] ?? ''), true);
            if (is_array($data)) {
                return $data;
            }

            $transport = trim((string) ($res['error'] ?? ''));

            return ['ok' => false, 'description' => $transport !== ''
                ? 'Нет связи с api.telegram.org: ' . $transport
                : 'Пустой ответ от api.telegram.org (HTTP ' . (int) ($res['status'] ?? 0) . '). '
                    . 'Возможно, хостинг блокирует исходящие запросы.'];
        };

        // 1. Токен. Bot API отдаёт 404 «Not Found» именно на неверный токен:
        // /bot<токен>/ — часть адреса, у несуществующего бота нет такого пути.
        $me = $call('getMe');
        if (empty($me['ok'])) {
            $steps[] = [
                'name' => 'Токен бота',
                'ok' => false,
                'text' => self::telegramHint((string) ($me['description'] ?? 'нет ответа')),
            ];

            return ['ok' => false, 'steps' => $steps];
        }
        $botId = (int) ($me['result']['id'] ?? 0);
        $botName = (string) ($me['result']['username'] ?? '');
        $steps[] = ['name' => 'Токен бота', 'ok' => true, 'text' => 'Бот найден: @' . $botName];

        // 2. Канал.
        $chat = $call('getChat', ['chat_id' => $chatId]);
        if (empty($chat['ok'])) {
            $steps[] = [
                'name' => 'Канал',
                'ok' => false,
                'text' => self::telegramHint((string) ($chat['description'] ?? 'нет ответа')),
            ];

            return ['ok' => false, 'steps' => $steps];
        }
        $steps[] = [
            'name' => 'Канал',
            'ok' => true,
            'text' => 'Канал найден: ' . (string) ($chat['result']['title'] ?? $chatId),
        ];

        // 3. Права: писать в канал может только администратор.
        $member = $call('getChatMember', ['chat_id' => $chatId, 'user_id' => $botId]);
        $status = (string) ($member['result']['status'] ?? '');
        if (empty($member['ok']) || !in_array($status, ['administrator', 'creator'], true)) {
            $steps[] = [
                'name' => 'Права бота',
                'ok' => false,
                'text' => 'Бот @' . $botName . ' не администратор канала'
                    . ($status !== '' ? ' (статус: ' . $status . ')' : '')
                    . '. Откройте канал → «Администраторы» → добавьте бота с правом публикации сообщений.',
            ];

            return ['ok' => false, 'steps' => $steps];
        }
        $steps[] = ['name' => 'Права бота', 'ok' => true, 'text' => 'Бот — администратор канала, публикация разрешена.'];

        return ['ok' => true, 'steps' => $steps];
    }

    /**
     * Перевод ответа Bot API в понятное действие. Сухие описания Telegram
     * («Not Found», «chat not found») ничего не говорят редактору о том, что
     * именно чинить.
     */
    public static function telegramHint(string $description): string
    {
        $d = mb_strtolower($description);

        if (str_contains($d, 'not found') && !str_contains($d, 'chat')) {
            return 'Токен бота неверен или отозван (Telegram отвечает «Not Found»). '
                . 'Проверьте токен у @BotFather: он выглядит как 1234567890:AA… — целиком, без слова «bot» и пробелов.';
        }
        if (str_contains($d, 'chat not found')) {
            return 'Канал не найден: проверьте chat_id. Для публичного канала — @имя_канала, '
                . 'для приватного — числовой id вида -100…, и бот должен быть добавлен в канал.';
        }
        if (str_contains($d, 'not enough rights') || str_contains($d, 'not a member') || str_contains($d, 'chat_write_forbidden')) {
            return 'У бота нет прав на публикацию: добавьте его администратором канала с правом отправки сообщений.';
        }
        if (str_contains($d, 'unauthorized')) {
            return 'Telegram отклонил токен (401 Unauthorized). Выпустите новый токен у @BotFather и сохраните здесь.';
        }
        if (str_contains($d, "can't parse entities") || str_contains($d, 'can\'t parse')) {
            return 'Telegram не принял разметку сообщения: проверьте HTML в подписи (допустимы <b>, <i>, <a href>).';
        }

        return $description;
    }

    /** Разбор ответа Bot API; для sendMediaGroup result — массив сообщений. */
    private function interpretTelegram(array $res, bool $group = false): array
    {
        $data = json_decode($res['body'] ?? '', true);
        if (is_array($data) && !empty($data['ok'])) {
            $msg = $group ? ($data['result'][0] ?? []) : ($data['result'] ?? []);
            $remoteId = isset($msg['message_id']) ? (string) $msg['message_id'] : null;

            return ['ok' => true, 'remote_id' => $remoteId, 'error' => null];
        }
        if (is_array($data) && isset($data['description'])) {
            // В журнал очереди пишем и подсказку, и исходный ответ Telegram:
            // первое нужно редактору, второе — при разборе нетипичных случаев.
            $raw = (string) $data['description'];
            $hint = self::telegramHint($raw);

            return self::err($hint === $raw ? $raw : $hint . ' (ответ Telegram: ' . $raw . ')');
        }
        $error = !empty($res['error']) ? (string) $res['error'] : 'HTTP ' . (int) ($res['status'] ?? 0);

        return self::err($error);
    }

    /**
     * Подпись поста Telegram: блоки языков (узбекский, затем русский),
     * ссылки на обе версии и подпись из настроек (HTML). Лимит жёсткий —
     * 1024 символа с фото, поэтому фиксированная часть (заголовки, ссылки,
     * подпись) резервируется, а остаток делится поровну между анонсами.
     *
     * @param array<string,mixed> $post
     * @param callable(string):string $esc
     */
    private static function telegramCaption(array $post, string $signature, int $limit, callable $esc): string
    {
        $langs = (array) ($post['langs'] ?? []);
        if ($langs === []) {
            // Запасной вариант для старых вызовов без языковых блоков.
            $langs = [[
                'title' => (string) ($post['title'] ?? ''),
                'excerpt' => trim((string) ($post['message'] ?? '')),
                'link' => (string) ($post['link'] ?? ''),
                'read_more' => 'Читать на сайте →',
            ]];
        }

        $sep = "\n\n———\n\n";
        $links = [];
        foreach ($langs as $l) {
            if (($l['link'] ?? '') !== '') {
                $links[] = '<a href="' . $esc((string) $l['link']) . '">' . $esc((string) $l['read_more']) . '</a>';
            }
        }
        $tail = ($links !== [] ? "\n\n" . implode(' | ', $links) : '')
            . ($signature !== '' ? "\n\n" . $signature : '');

        // Считаем фиксированную часть: заголовки + разделители + хвост.
        $fixed = mb_strlen(strip_tags($tail)) + (count($langs) - 1) * mb_strlen(strip_tags($sep));
        foreach ($langs as $l) {
            $fixed += mb_strlen((string) $l['title']) + 2; // +2 — перенос строки после заголовка
        }
        $available = max(0, $limit - $fixed - 4);
        $perLang = count($langs) > 0 ? (int) floor($available / count($langs)) : 0;

        $parts = [];
        foreach ($langs as $l) {
            $title = trim((string) $l['title']);
            $excerpt = trim((string) ($l['excerpt'] ?? ''));
            if ($perLang > 0 && mb_strlen($excerpt) > $perLang) {
                $excerpt = rtrim(mb_substr($excerpt, 0, max(0, $perLang - 1))) . '…';
            } elseif ($perLang <= 0) {
                $excerpt = '';
            }
            $parts[] = ($title !== '' ? '<b>' . $esc($title) . '</b>' : '')
                . ($excerpt !== '' ? "\n\n" . $esc($excerpt) : '');
        }

        return implode($sep, $parts) . $tail;
    }

    /**
     * Текст поста для сетей без разметки (Facebook/LinkedIn/Instagram):
     * блоки языков подряд и голые URL — платформы линкуют их сами
     * (в Instagram ссылки некликабельны, там подпись обычно с хештегами).
     *
     * @param array<string,mixed> $post
     */
    private static function plainMessage(array $post, string $signature, int $limit = 0): string
    {
        $langs = (array) ($post['langs'] ?? []);
        if ($langs === []) {
            $text = trim((string) ($post['message'] ?? '')) . "\n\n" . (string) ($post['link'] ?? '');
        } else {
            $parts = [];
            foreach ($langs as $l) {
                $title = trim((string) $l['title']);
                $excerpt = trim((string) ($l['excerpt'] ?? ''));
                $parts[] = $title . ($excerpt !== '' ? "\n\n" . $excerpt : '') . "\n" . (string) $l['link'];
            }
            $text = implode("\n\n———\n\n", $parts);
        }
        if ($signature !== '') {
            $text .= "\n\n" . $signature;
        }
        $text = trim($text);
        if ($limit > 0 && mb_strlen($text) > $limit) {
            $text = rtrim(mb_substr($text, 0, $limit - 1)) . '…';
        }

        return $text;
    }

    private function facebook(array $cfg, array $post): array
    {
        if (empty($cfg['token']) || empty($cfg['page_id'])) {
            return self::err('Не заданы токен или ID страницы Facebook.');
        }
        $url = self::GRAPH . '/' . rawurlencode($cfg['page_id']) . '/feed';
        $body = http_build_query([
            'message' => self::plainMessage($post, (string) ($cfg['signature'] ?? '')),
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
                    // LinkedIn: лимит текста поста — 3000 символов.
                    'shareCommentary' => ['text' => self::plainMessage($post, (string) ($cfg['signature'] ?? ''), 3000)],
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
            // Instagram: лимит подписи — 2200 символов.
            'caption' => self::plainMessage($post, (string) ($cfg['signature'] ?? ''), 2200),
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
