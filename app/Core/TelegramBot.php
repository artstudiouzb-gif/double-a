<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;

/**
 * Бесплатная доставка кодов входа через собственного Telegram-бота (Bot API).
 * Токен выдаёт @BotFather бесплатно; сообщения приходят от вашего бота.
 * Привязка аккаунта: админ отправляет боту одноразовый код из профиля, CMS
 * находит его через getUpdates и сохраняет chat_id (без вебхуков).
 *
 * Без сторонних библиотек: нативный curl.
 */
final class TelegramBot
{
    private const API_BASE = 'https://api.telegram.org';
    private const TIMEOUT_SECONDS = 10;

    public static function isConfigured(): bool
    {
        return trim(Setting::get('telegram_bot_token', '')) !== '';
    }

    /** Информация о боте (username для ссылки t.me/...); null при ошибке. */
    public static function getMe(): ?array
    {
        $res = self::request('getMe', []);

        return is_array($res) ? $res : null;
    }

    /** Отправляет одноразовый код входа привязанному аккаунту. */
    public static function sendLoginCode(int $chatId, string $code): bool
    {
        $text = "\u{1F510} Код входа в панель управления: {$code}\n"
            . "Действует 5 минут. Никому не сообщайте этот код.";

        return self::request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]) !== null;
    }

    /**
     * Ищет в свежих сообщениях бота одноразовый код привязки и возвращает
     * chat_id отправителя. Используется страницей профиля («Проверить
     * привязку»): админ отправляет боту код, CMS забирает его getUpdates.
     */
    public static function findChatIdByCode(string $code): ?int
    {
        $updates = self::request('getUpdates', ['limit' => 100, 'allowed_updates' => ['message']]);
        if (!is_array($updates)) {
            return null;
        }

        return self::matchUpdates($updates, $code);
    }

    /**
     * Чистая логика сопоставления getUpdates с кодом привязки (тестируемо).
     * Принимает и «CODE», и «/start CODE».
     *
     * @param array<int,array<string,mixed>> $updates
     */
    public static function matchUpdates(array $updates, string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        // Идём с конца — берём самое свежее совпадение.
        foreach (array_reverse($updates) as $update) {
            $msg = $update['message'] ?? null;
            if (!is_array($msg)) {
                continue;
            }
            $text = trim((string) ($msg['text'] ?? ''));
            if ($text === $code || $text === '/start ' . $code) {
                $chatId = $msg['chat']['id'] ?? null;
                if (is_int($chatId) || (is_string($chatId) && ctype_digit($chatId))) {
                    return (int) $chatId;
                }
            }
        }

        return null;
    }

    /**
     * Вызов метода Bot API. Возвращает поле result либо null при любой ошибке
     * (сбои логируются; секреты и коды в логи не пишутся).
     *
     * @param array<string,mixed> $params
     */
    private static function request(string $method, array $params): mixed
    {
        $token = trim(Setting::get('telegram_bot_token', ''));
        if ($token === '') {
            return null;
        }

        // База переопределяется переменной окружения только для тестов.
        $base = getenv('TELEGRAM_BOT_URL') ?: self::API_BASE;
        $url = rtrim($base, '/') . '/bot' . $token . '/' . $method;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            Logger::warning('Telegram Bot API недоступен: ' . $curlError, ['method' => $method]);
            return null;
        }

        $json = json_decode((string) $body, true);
        if ($httpCode !== 200 || !is_array($json) || ($json['ok'] ?? false) !== true) {
            Logger::warning('Telegram Bot API вернул ошибку', [
                'method' => $method,
                'http' => $httpCode,
                'error' => is_array($json) ? (string) ($json['description'] ?? '') : 'bad response',
            ]);
            return null;
        }

        return $json['result'] ?? null;
    }
}
