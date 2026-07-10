<?php

declare(strict_types=1);

/*
 * Скопируйте этот файл в config.php и заполните реальными данными,
 * либо задайте те же значения через переменные окружения хостинга
 * (APP_ENV, APP_DEBUG, APP_URL, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD и т.д.)
 */

return [
    'app' => [
        'env' => getenv('APP_ENV') ?: 'production',
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'url' => getenv('APP_URL') ?: 'https://example.com',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Europe/Moscow',
    ],
    // Критические ключи жизнеобеспечения — строго из файла/окружения, не из БД
    // (задача 115). Падение БД не влияет на их чтение.
    'crypto' => [
        'app_env' => getenv('APP_ENV') ?: 'production',
        'app_url' => getenv('APP_URL') ?: '',
        // Ключ шифрования для будущего шифрования секретов at-rest
        // (напр. TOTP-секретов). Сгенерируйте: bin2hex(random_bytes(32)).
        'encryption_key' => getenv('APP_ENCRYPTION_KEY') ?: '',
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'artstudio_cms',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => 'asc_session',
        'lifetime' => 7200,
    ],
    'security' => [
        'login_max_attempts' => 5,
        'login_lockout_minutes' => 15,
        'login_attempts_window_minutes' => 15,
        // HSTS с preload (hstspreload.org): включать только после месяца
        // стабильной работы по HTTPS на всех поддоменах — снять быстро нельзя.
        'hsts_preload' => false,
    ],
    'paths' => [
        'protected_uploads' => __DIR__ . '/../storage/protected_uploads',
        'public_uploads' => __DIR__ . '/../public/uploads/public',
        'public_uploads_url' => '/uploads/public',
    ],
    'mail' => [
        // Если host пуст — письма не отправляются (уведомления просто пропускаются).
        'host' => getenv('SMTP_HOST') ?: '',
        'port' => (int) (getenv('SMTP_PORT') ?: 587),
        // 'tls' (STARTTLS, порт 587), 'ssl' (implicit TLS, порт 465) или 'none'.
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'from_email' => getenv('SMTP_FROM_EMAIL') ?: '',
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'ArtStudio CMS',
        'timeout' => 15,
    ],
    // Telegram-алертинг (задача 59). Читается строго из файла/окружения, не из
    // БД — чтобы падение БД не мешало отправке критических оповещений.
    'telegram' => [
        'bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
        'chat_id' => getenv('TELEGRAM_CHAT_ID') ?: '',
        // Опциональный отдельный чат для событий безопасности (SECURITY).
        'chat_id_security' => getenv('TELEGRAM_CHAT_ID_SECURITY') ?: '',
        // Минимальный уровень для отправки в Telegram: INFO|WARNING|ERROR|CRITICAL.
        // Ниже этого уровня события пишутся только в файл. SECURITY отправляется
        // всегда (если Telegram настроен).
        'min_level' => getenv('TELEGRAM_MIN_LEVEL') ?: 'WARNING',
    ],
    // Согласованные бэкапы (задача 1.2). backup_worker снимает дамп БД + файлы
    // одним проходом, кладёт рядом .sha256, ротирует старые копии и (если задан
    // external_dir) дублирует архив во внешнее хранилище.
    'backup' => [
        // Сколько дней хранить локальные копии (старше — удаляются при ротации).
        // Умная ротация: хранить все копии за keep_daily последних дней + по
        // одной (самой свежей) на неделю за keep_weekly недель. keep_daily = 0
        // отключает умную схему — тогда действует простая retention_days.
        'keep_daily' => (int) (getenv('BACKUP_KEEP_DAILY') ?: 7),
        'keep_weekly' => (int) (getenv('BACKUP_KEEP_WEEKLY') ?: 4),
        'retention_days' => (int) (getenv('BACKUP_RETENTION_DAYS') ?: 14),
        // Внешний каталог для копии архива (смонтированное сетевое хранилище,
        // rclone/S3-FUSE и т.п.). Пусто — копия только локально. КРИТИЧНО для
        // защиты от полного отказа сервера: локальная копия при этом бесполезна.
        'external_dir' => getenv('BACKUP_EXTERNAL_DIR') ?: '',
        // Включать режим обслуживания на время снятия дампа — гарантирует
        // согласованность БД и файлов (никаких загрузок в момент бэкапа).
        'maintenance_during' => filter_var(getenv('BACKUP_MAINTENANCE_DURING') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    ],
];
