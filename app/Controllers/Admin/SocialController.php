<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Heartbeat;
use App\Core\SocialPublisher;
use App\Core\SocialSettings;
use App\Core\View;
use App\Models\Setting;
use App\Models\SocialPost;

/**
 * Настройки авто-публикации в соцсети (Facebook / LinkedIn / Instagram).
 * Токены — чувствительные данные, поэтому раздел доступен только
 * супер-администратору.
 */
final class SocialController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();

        $config = [];
        foreach (SocialPublisher::NETWORKS as $net) {
            $config[$net] = [
                'enabled' => SocialSettings::isEnabled($net),
                'ready' => SocialSettings::isReady($net),
                'fields' => SocialSettings::configFor($net),
            ];
        }

        View::render('admin/settings/social', [
            'config' => $config,
            'queueLog' => SocialPost::recent(40),
            'queueCounts' => SocialPost::counts(),
            'workerStatus' => Heartbeat::status()['social'] ?? null,
        ]);
    }

    /**
     * Запуск обработки очереди публикаций прямо из админки — то же, что делает
     * Cron-воркер. Полезно, если Cron ещё не настроен на хостинге или нужно
     * дослать «зависшие» pending без ожидания расписания.
     */
    public function runNow(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        $res = SocialSettings::dispatchQueue(50);
        if ($res['taken'] === 0) {
            Flash::success('Очередь пуста — отправлять нечего.');
        } elseif ($res['failed'] === 0) {
            Flash::success("Отправлено: {$res['sent']}.");
        } elseif ($res['sent'] === 0) {
            Flash::error("Не удалось отправить: {$res['failed']}. Подробности — в журнале ниже.");
        } else {
            Flash::success("Отправлено: {$res['sent']}, с ошибкой: {$res['failed']} (см. журнал).");
        }

        header('Location: /admin/social');
        exit;
    }

    /**
     * Проверка подключения к Telegram без публикации: отвечает, что именно не
     * так — токен, канал или права бота. Ответ Bot API «Not Found» в журнале
     * очереди сам по себе не подсказывает, где чинить.
     */
    public function checkTelegram(): void
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
            Flash::success('Telegram настроен верно — можно публиковать.');
        }

        header('Location: /admin/social');
        exit;
    }

    public function update(): void
    {
        Auth::requireSuperAdmin();
        Csrf::verifyRequest();

        foreach (SocialPublisher::NETWORKS as $net) {
            Setting::set('social_' . $net . '_enabled', !empty($_POST[$net]['enabled']) ? '1' : '0');
            foreach (SocialSettings::FIELDS[$net] as $field) {
                Setting::set('social_' . $net . '_' . $field, trim((string) ($_POST[$net][$field] ?? '')));
            }
        }

        Flash::success('Настройки соцсетей сохранены.');

        // Токен Telegram — часть адреса запроса, поэтому мусор в нём даёт не
        // внятную ошибку, а сухое «Not Found» из Bot API. Предупреждаем сразу
        // при сохранении, а не после неудачной публикации.
        $token = trim((string) ($_POST['telegram']['token'] ?? ''));
        if ($token !== '' && !preg_match('/^\d{6,}:[A-Za-z0-9_-]{30,}$/', $token)) {
            Flash::error(
                'Токен Telegram выглядит неверно: ожидается вид 1234567890:AA… (цифры, двоеточие, ключ). '
                . 'Часто по ошибке копируют слово «bot» в начале или имя бота вместо токена. '
                . 'Проверьте кнопкой «Проверить подключение к Telegram».'
            );
        }

        header('Location: /admin/social');
        exit;
    }
}
