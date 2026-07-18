<?php

declare(strict_types=1);

use App\Core\SocialSettings;
use App\Models\Setting;

test('Telegram: публикация берёт токен бота, если отдельный не задан (БД)', function () {
    ensure_test_db();
    $botBackup = Setting::get('telegram_bot_token', '');
    $ownBackup = Setting::get('social_telegram_token', '');

    Setting::set('telegram_bot_token', '111111:MAIN_BOT_TOKEN_VALUE_FOR_TESTS_1');
    Setting::set('social_telegram_token', '');
    $cfg = SocialSettings::configFor('telegram');
    assert_same('111111:MAIN_BOT_TOKEN_VALUE_FOR_TESTS_1', $cfg['token'], 'один бот на вход и публикацию');

    // Отдельный бот для публикаций — если задан, он в приоритете.
    Setting::set('social_telegram_token', '222222:OWN_PUBLISHER_TOKEN_VALUE_TEST');
    $cfg = SocialSettings::configFor('telegram');
    assert_same('222222:OWN_PUBLISHER_TOKEN_VALUE_TEST', $cfg['token']);

    // Ни того ни другого — пусто, публикатор сам сообщит о незаполненных настройках.
    Setting::set('telegram_bot_token', '');
    Setting::set('social_telegram_token', '');
    $cfg = SocialSettings::configFor('telegram');
    assert_same('', $cfg['token']);

    Setting::set('telegram_bot_token', (string) $botBackup);
    Setting::set('social_telegram_token', (string) $ownBackup);
});

test('Настройки сайта не трогают ключи Telegram (иначе стёрли бы токен)', function () {
    // Поля Telegram убраны из формы «Настройки»; если ключи останутся в списке
    // сохраняемых, каждое сохранение настроек обнулит токен и выключит вход.
    $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/SettingsController.php');
    $keysBlock = substr($src, (int) strpos($src, 'TEXT_KEYS'), 400);

    assert_not_contains('telegram_bot_token', $keysBlock);
    assert_not_contains('telegram_gateway_token', $keysBlock);
    assert_not_contains('telegram_notify_chat_ids', $keysBlock);
});

test('Соцсети не сохраняют Telegram: у него свой раздел', function () {
    $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/SocialController.php');
    // Форма соцсетей идёт по formNetworks(), где Telegram исключён — иначе
    // пустой POST обнулил бы chat_id и подпись канала.
    assert_contains('formNetworks', $src);
    assert_contains("net !== 'telegram'", $src);

    $view = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/settings/social.php');
    assert_contains('/admin/telegram', $view, 'из соцсетей есть ссылка в раздел Telegram');
});

test('Раздел Telegram: все шаги подключения на одной странице', function () {
    $view = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/admin/telegram/index.php');
    foreach ([
        '/admin/telegram/bot',            // 1. токен
        '/admin/telegram/bot/check',      // проверка бота
        '/admin/telegram/link',           // 2. привязка и коды входа
        '/admin/telegram/channel',        // 3. канал
        '/admin/telegram/channel/check',  // проверка канала и прав
        '/admin/telegram/extras',         // 4. уведомления и Gateway
    ] as $action) {
        assert_contains($action, $view);
    }

    $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
    assert_contains("'/admin/telegram'", $routes);
    assert_contains("'/admin/telegram/channel/check'", $routes);
    // Старые точки входа из раздела соцсетей убраны — один путь, а не два.
    assert_not_contains('/admin/social/check-telegram', $routes);
    assert_not_contains('/admin/social/use-login-bot-token', $routes);
});
