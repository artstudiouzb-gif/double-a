# ArtStudio CMS

Лёгкая CMS на чистом PHP 8.2+ и MySQL/MariaDB без систем плагинов и тем.
Весь функционал зашит в ядро, дизайн страниц собирается из модульных
блоков (page builder) с изолированными (scoped) стилями.

Без Composer и внешних библиотек — только стандартные расширения PHP
(`pdo_mysql`, `session`, `hash`, `openssl`/`random_bytes`).

## Требования

- PHP 8.2+
- Расширения: `pdo_mysql`, `mbstring`, `json`
- MySQL 8.0+ или MariaDB 10.5+
- Apache с `mod_rewrite` (или эквивалент правил для nginx, см. ниже)

## Установка

1. **База данных.** Создайте базу и импортируйте схему:

   ```bash
   mysql -u root -p -e "CREATE DATABASE artstudio_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -u root -p artstudio_cms < database/schema.sql
   ```

2. **Конфигурация.** Скопируйте `config/config.example.php` в
   `config/config.php` и укажите реальные данные подключения к БД
   (или задайте переменные окружения `DB_HOST`, `DB_DATABASE`,
   `DB_USERNAME`, `DB_PASSWORD`, `APP_URL`, `APP_ENV=production` на
   стороне хостинга — тогда редактировать файл не нужно).

3. **Document root.** Направьте document root домена на папку
   `/public` — там лежит единственная точка входа `index.php` и
   `download.php`. Если хостинг не позволяет сменить document root
   (домен = корень аккаунта), в корне проекта уже лежит `.htaccess`,
   прозрачно проксирующий все запросы в `/public`.

4. **Права на папки.** Веб-серверу нужен доступ на запись в:
   - `storage/logs/`
   - `storage/protected_uploads/` (защищённые файлы, хранится вне `public/`)
   - `public/uploads/public/` (публичные файлы, отдаются напрямую)

5. **Первый администратор.** Аккаунтов по умолчанию нет — создайте
   первого администратора через консоль:

   ```bash
   php database/create_admin.php
   ```

   При первом входе в `/admin/login` система обязательно предложит
   подключить 2FA (TOTP) — без этого доступ к дашборду не выдаётся.

6. Откройте `https://ваш-домен/admin/login`.

## Nginx (если не Apache)

```nginx
root /path/to/artstudio/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}

location ~* ^/(storage|config|app|templates|database)/ {
    deny all;
}
```

## Архитектура

```
artstudio/
├── public/                    # document root
│   ├── index.php              # единая точка входа сайта и админки
│   ├── download.php           # контроллер отдачи защищённых файлов
│   ├── .htaccess
│   ├── assets/{css,js}/       # статика
│   └── uploads/public/        # публичные файлы (прямые ссылки)
├── app/
│   ├── Core/                  # ядро: БД, роутер, Auth, TOTP, CSRF,
│   │                          # RateLimiter, CssScoper, BlockRenderer
│   ├── Controllers/
│   │   ├── Admin/             # контроллеры панели управления
│   │   └── Site/              # контроллеры публичного сайта
│   ├── Models/                # PDO-модели (prepared statements)
│   └── Views/                 # PHP-шаблоны (admin/*, site/*, errors/*)
├── templates/blocks/          # HTML-шаблоны блоков конструктора страниц
├── config/                    # config.php (не в git) / config.example.php
├── database/
│   ├── schema.sql             # полная схема БД
│   └── create_admin.php       # CLI-утилита создания администратора
└── storage/
    ├── logs/
    └── protected_uploads/     # защищённые файлы, ВНЕ document root
```

Автозагрузка классов — простой PSR-4-подобный `spl_autoload_register`
в `app/Core/bootstrap.php` (namespace `App\` → папка `app/`), без
Composer.

## Безопасность входа в панель управления

- Пароли хранятся через `password_hash()` (bcrypt, cost 12).
- Обязательная 2FA (TOTP, RFC 6238) — реализована вручную в
  `App\Core\TOTP`, без внешних библиотек. Совместима с Google
  Authenticator, Яндекс Ключ и любым другим TOTP-приложением.
  Секретный ключ вводится вручную (без QR, чтобы не тянуть зависимости
  и не обращаться к сторонним сервисам генерации QR-кодов).
- Сессии: `HttpOnly`, `Secure` (авто-определение HTTPS), `SameSite=Lax`,
  `session.use_strict_mode`, регенерация ID при каждом повышении
  привилегий (после пароля, после 2FA).
- Rate limiting на вход и на проверку 2FA-кода: блокировка по паре
  `IP + логин` после `security.login_max_attempts` неудачных попыток
  в течение `security.login_attempts_window_minutes` минут
  (`app/Core/RateLimiter.php`, таблица `login_attempts`).
- CSRF-токен на всех POST-формах (`App\Core\Csrf`).

## Конструктор страниц и изоляция стилей блоков

Каждая страница (`pages`) хранит набор блоков (`blocks`, поле
`page_id`, `sort_order`). У блока есть `type`, JSON `data` и
произвольный `custom_css`.

При рендере (`App\Core\BlockRenderer::render()`):

1. HTML блока рендерится из шаблона `templates/blocks/{type}.php` и
   оборачивается в `<section id="block-{id}" class="cms-block ...">`.
2. `custom_css` блока прогоняется через `App\Core\CssScoper::scope()`,
   который разбирает CSS по фигурным скобкам (а не наивной регуляркой)
   и добавляет префикс `#block-{id}` к каждому селектору верхнего
   уровня, рекурсивно спускаясь внутрь `@media`/`@supports` и оставляя
   `@keyframes`/`@font-face` без изменений. Также вырезаются `@import`,
   `javascript:`, `expression()` — типовые векторы CSS-based атак.
3. Итоговый CSS каждого блока собирается в единый `<style>` в `<head>`
   страницы (`App\Core\BlockRenderer::renderPage()`), поэтому стили
   одного блока физически не могут задеть селекторы другого блока или
   общий дизайн сайта.

Готовые типы блоков: `text`, `html`, `cta`, `advantages`, `slider`,
`gallery`, `form`. Новый тип блока добавляется одним файлом в
`templates/blocks/`.

## Файловый менеджер

- **Публичные файлы** — `public/uploads/public/`, отдаются веб-сервером
  напрямую по ссылке вида `/uploads/public/<stored_name>`.
- **Защищённые файлы** — `storage/protected_uploads/`, физически лежат
  **вне** document root (`public/`), поэтому недоступны по прямой
  ссылке в принципе, независимо от конфигурации `.htaccess`. Отдаются
  только через `download.php?file_id=X`, который проверяет:
  1. авторизован ли пользователь в панели управления (активная сессия), либо
  2. передан ли верный `token`, совпадающий с `files.access_token`
     (`hash_equals`, защита от timing-атак).

  Путь к файлу дополнительно проверяется через `realpath()` +
  `str_starts_with()` относительно базовой директории — защита от
  path traversal, даже если `stored_name` когда-либо будет неконтролируемым.

## Что реализовано на этом этапе

1. Полная схема БД (`database/schema.sql`) — пользователи, новости,
   страницы, блоки, проекты (+ галерея + кастомные поля), команда,
   формы + заявки, настройки, файлы, `login_attempts`.
2. Структура проекта (см. выше).
3. Ядро безопасности: логин + обязательная 2FA (TOTP), rate limiting,
   CSRF, безопасные сессии.
4. Механизм рендера страниц из блоков с изоляцией CSS.
5. `download.php` — контроллер безопасной отдачи защищённых файлов.

## Что будет добавлено на следующем этапе

CRUD-разделы админ-панели (Новости, Страницы + визуальный редактор
блоков, Проекты, Команда, Формы + просмотр заявок, Настройки дизайна),
загрузчик файлов с UI файлового менеджера, приём отправок форм
(`/forms/{slug}/submit`), листинг новостей на сайте.
