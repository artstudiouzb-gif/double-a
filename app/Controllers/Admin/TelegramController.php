<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\SocialPublisher;
use App\Core\SocialSettings;
use App\Core\TelegramBot;
use App\Core\TelegramGateway;
use App\Core\TelegramLink;
use App\Core\View;
use App\Models\Setting;
use App\Models\User;

/**
 * Единый раздел «Telegram»: бот, привязка администратора, публикация в канал и
 * уведомления о заявках. Раньше эти настройки лежали в трёх местах
 * («Настройки», «Профиль», «Соцсети»), и было неочевидно, что токен бота для
 * входа и токен публикации — разные поля.
 *
 * Порядок на странице повторяет порядок подключения: токен → привязка →
 * канал → уведомления.
 */
final class TelegramController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();

        $botToken = trim((string) Setting::get('telegram_bot_token', ''));
        $me = $botToken !== '' ? TelegramBot::getMe() : null;
        $user = User::findById((int) Auth::id());

        View::render('admin/telegram/index', [
            'botToken' => $botToken,
            'botUsername' => is_array($me) ? (string) ($me['username'] ?? '') : '',
            'botOk' => is_array($me),
            'linked' => (int) ($user['telegram_chat_id'] ?? 0) > 0,
            'myChatId' => (int) ($user['telegram_chat_id'] ?? 0),
            // Код показываем только пока не привязан — иначе он лишний шум.
            'linkCode' => ($botToken !== '' && (int) ($user['telegram_chat_id'] ?? 0) === 0)
                ? TelegramLink::code()
                : null,
            'channel' => SocialSettings::configFor('telegram'),
            'channelOwnToken' => trim((string) Setting::get('social_telegram_token', '')),
            'channelEnabled' => SocialSettings::isEnabled('telegram'),
            'notifyChatIds' => (string) Setting::get('telegram_notify_chat_ids', ''),
            'gatewayToken' => (string) Setting::get('telegram_gateway_token', ''),
            'gatewayConfigured' => TelegramGateway::isConfigured(),
        ]);
    }

    /** Шаг 1: токен бота. */
    public function saveBot(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $token = trim((string) ($_POST['telegram_bot_token'] ?? ''));
        Setting::set('telegram_bot_token', $token);

        if ($token !== '' && !self::looksLikeToken($token)) {
            Flash::error(
                'Токен сохранён, но выглядит непривычно: ожидается вид 1234567890:AA… '
                . 'Часто копируют слово «bot» в начале или имя бота вместо токена.'
            );
        } else {
            Flash::success('Токен бота сохранён.');
        }

        header('Location: /admin/telegram');
        exit;
    }

    /** Шаг 1: проверка токена через getMe. */
    public function checkBot(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $me = TelegramBot::getMe();
        if (is_array($me)) {
            Flash::success('Бот отвечает: @' . (string) ($me['username'] ?? '') . '.');
        } else {
            Flash::error(
                'Бот не отвечает. Telegram отклоняет токен («Not Found» приходит именно на неверный токен). '
                . 'Возьмите токен у @BotFather: /mybots → ваш бот → API Token.'
            );
        }

        header('Location: /admin/telegram');
        exit;
    }

    /** Шаг 2: подтверждение привязки своего аккаунта. */
    public function link(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $res = TelegramLink::confirm((int) Auth::id());
        if ($res['ok']) {
            Flash::success($res['message']);
        } else {
            Flash::error($res['message']);
        }

        header('Location: /admin/telegram');
        exit;
    }

    /** Шаг 3: канал для публикации новостей. */
    public function saveChannel(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        Setting::set('social_telegram_chat_id', trim((string) ($_POST['chat_id'] ?? '')));
        Setting::set('social_telegram_signature', trim((string) ($_POST['signature'] ?? '')));
        Setting::set('social_telegram_enabled', !empty($_POST['enabled']) ? '1' : '0');

        // Отдельный бот для публикаций — необязательное переопределение.
        // Пусто = используется основной бот из шага 1.
        $ownToken = trim((string) ($_POST['own_token'] ?? ''));
        Setting::set('social_telegram_token', $ownToken);
        if ($ownToken !== '' && !self::looksLikeToken($ownToken)) {
            Flash::error('Отдельный токен публикации выглядит непривычно: ожидается вид 1234567890:AA…');
        }

        Flash::success('Настройки канала сохранены.');
        header('Location: /admin/telegram');
        exit;
    }

    /** Шаг 3: проверка канала и прав бота в нём. */
    public function checkChannel(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $result = (new SocialPublisher())->checkTelegram(SocialSettings::configFor('telegram'));
        foreach ($result['steps'] as $step) {
            $line = $step['name'] . ': ' . $step['text'];
            if ($step['ok']) {
                Flash::success($line);
            } else {
                Flash::error($line);
            }
        }
        if ($result['ok']) {
            Flash::success('Публикация настроена верно.');
        }

        header('Location: /admin/telegram');
        exit;
    }

    /** Шаг 4: получатели уведомлений о заявках форм и резервный Gateway. */
    public function saveExtras(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        Setting::set('telegram_notify_chat_ids', trim((string) ($_POST['telegram_notify_chat_ids'] ?? '')));
        Setting::set('telegram_gateway_token', trim((string) ($_POST['telegram_gateway_token'] ?? '')));

        Flash::success('Дополнительные настройки сохранены.');
        header('Location: /admin/telegram');
        exit;
    }

    /** Токен Bot API: цифры, двоеточие, ключ. */
    private static function looksLikeToken(string $token): bool
    {
        return (bool) preg_match('/^\d{6,}:[A-Za-z0-9_-]{30,}$/', $token);
    }
}
