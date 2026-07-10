-- ArtStudio CMS — полная схема базы данных
-- MySQL 8.0+ / MariaDB 10.5+
-- Кодировка: utf8mb4, движок: InnoDB

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Пользователи админ-панели
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(60)  NOT NULL,
    email           VARCHAR(190) NOT NULL,
    phone           VARCHAR(20)  NULL COMMENT 'телефон в формате E.164 (+998...) для кода входа через Telegram',
    telegram_chat_id BIGINT      NULL COMMENT 'chat_id привязанного Telegram-аккаунта (коды входа через бота)',
    password_hash   VARCHAR(255) NOT NULL,
    totp_secret     VARCHAR(64)  NULL,
    totp_enabled    TINYINT(1)   NOT NULL DEFAULT 0,
    role            ENUM('admin', 'editor') NOT NULL DEFAULT 'admin',
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Попытки входа (для Rate Limiting / защиты от перебора)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier      VARCHAR(190) NOT NULL COMMENT 'ip|username или ip|2fa|username',
    ip_address      VARCHAR(45)  NOT NULL,
    success         TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_attempts_identifier (identifier, attempted_at),
    KEY idx_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Новости
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS news (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL,
    excerpt         TEXT NULL,
    badge           VARCHAR(100) NULL COMMENT 'бейдж категории детальной страницы',
    content         LONGTEXT NULL,
    image           VARCHAR(255) NULL,
    video_url       VARCHAR(255) NULL,
    press_release_url VARCHAR(255) NULL,
    key_points      TEXT NULL COMMENT 'ключевые тезисы, по одному на строку',
    event_meta      TEXT NULL COMMENT 'карточка «О мероприятии», по строке на пункт',
    docs            TEXT NULL COMMENT 'JSON-список документов [{title, meta, url}]',
    source_note     VARCHAR(255) NULL COMMENT 'подпись источника (пресс-служба)',
    views           INT UNSIGNED NOT NULL DEFAULT 0,
    layout_type     ENUM('standard','gallery','video','side_image','premium') NOT NULL DEFAULT 'standard',
    focal_x         TINYINT UNSIGNED NULL COMMENT 'фокальная точка обложки X, %',
    focal_y         TINYINT UNSIGNED NULL COMMENT 'фокальная точка обложки Y, %',
    meta_title      VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    status          ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    published_at    DATETIME NULL,
    author_id       INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL COMMENT 'мягкое удаление (корзина)',
    UNIQUE KEY uq_news_slug (slug),
    KEY idx_news_status_published (status, published_at),
    CONSTRAINT fk_news_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переводы новостей (для НЕ-дефолтных языков)
CREATE TABLE IF NOT EXISTS news_translations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    news_id         INT UNSIGNED NOT NULL,
    lang            VARCHAR(8) NOT NULL,
    title           VARCHAR(255) NULL,
    excerpt         TEXT NULL,
    content         LONGTEXT NULL,
    meta_title      VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    UNIQUE KEY uq_news_translations (news_id, lang),
    CONSTRAINT fk_news_translations_news FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Галерея фотографий новости (этап 12.1)
CREATE TABLE IF NOT EXISTS news_images (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    news_id     INT UNSIGNED NOT NULL,
    path        VARCHAR(255) NOT NULL,
    alt_text    VARCHAR(255) NULL,
    focal_x     TINYINT UNSIGNED NULL COMMENT 'фокальная точка X, %',
    focal_y     TINYINT UNSIGNED NULL COMMENT 'фокальная точка Y, %',
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_news_images_news (news_id, sort_order),
    CONSTRAINT fk_news_images_news FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Языки сайта (управляемый список для мультиязычности)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS languages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(8) NOT NULL COMMENT 'ISO-код: ru, uz, en',
    name            VARCHAR(60) NOT NULL COMMENT 'отображаемое название: Русский, Oʻzbekcha',
    is_default      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'язык по умолчанию (URL без префикса)',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_languages_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO languages (code, name, is_default, is_active, sort_order) VALUES
    ('ru', 'Русский', 1, 1, 0),
    ('uz', 'Oʻzbekcha', 0, 1, 1)
ON DUPLICATE KEY UPDATE code = code;

-- ---------------------------------------------------------------------------
-- Страницы (статические, собираются из блоков)
-- Базовая строка = контент на языке по умолчанию; переводы в page_translations.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL COMMENT 'заголовок на языке по умолчанию',
    slug            VARCHAR(255) NOT NULL,
    meta_title      VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    status          ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    is_home         TINYINT(1) NOT NULL DEFAULT 0,
    layout_type     ENUM('no_sidebar', 'left_sidebar', 'right_sidebar') NOT NULL DEFAULT 'no_sidebar',
    hide_chrome     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'лендинг: скрыть шапку/футер сайта',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL COMMENT 'мягкое удаление (корзина)',
    UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Гарантия, что домашней может быть только одна страница обеспечивается на уровне приложения
-- (перед установкой is_home = 1 остальные страницы сбрасываются в транзакции).

-- Переводы страниц (заголовок и мета для НЕ-дефолтных языков)
CREATE TABLE IF NOT EXISTS page_translations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id         INT UNSIGNED NOT NULL,
    lang            VARCHAR(8) NOT NULL,
    title           VARCHAR(255) NULL,
    meta_title      VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    UNIQUE KEY uq_page_translations (page_id, lang),
    CONSTRAINT fk_page_translations_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Блоки конструктора страниц (Page Builder)
-- Каждый блок принадлежит паре (page_id, lang): у каждого языка свой стек блоков.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id         INT UNSIGNED NOT NULL,
    parent_block_id INT UNSIGNED NULL COMMENT 'родительский блок columns (группа 4.1); NULL = верхний уровень',
    column_index    INT NOT NULL DEFAULT 0 COMMENT 'номер колонки внутри родителя columns',
    lang            VARCHAR(8) NOT NULL DEFAULT '' COMMENT 'код языка стека блоков',
    type            VARCHAR(60) NOT NULL COMMENT 'text, slider, advantages, cta, gallery, form, html, columns, testimonials, counters, team_list, projects_list',
    title           VARCHAR(255) NULL COMMENT 'внутреннее название блока для админки',
    data            JSON NOT NULL COMMENT 'структурированные данные блока',
    custom_css      TEXT NULL COMMENT 'CSS блока, изолируется при рендере через #block-{id}',
    sort_order      INT NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'блок выводится на сайте (0 — скрыт, но не удалён)',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_blocks_page (page_id, lang, sort_order),
    KEY idx_blocks_parent (parent_block_id, column_index, sort_order),
    CONSTRAINT fk_blocks_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    CONSTRAINT fk_blocks_parent FOREIGN KEY (parent_block_id) REFERENCES blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- История версий блоков (группа 5.1). Снимок состояния блока перед каждой
-- перезаписью; хранятся последние 20 ревизий на блок.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS block_revisions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    block_id        INT UNSIGNED NOT NULL,
    title           VARCHAR(255) NULL,
    data            JSON NOT NULL,
    custom_css      TEXT NULL,
    created_by      INT UNSIGNED NULL COMMENT 'автор изменения (users.id), NULL если неизвестен',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_block_revisions_block (block_id, id),
    CONSTRAINT fk_block_revisions_block FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Проекты (портфолио)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL,
    description     LONGTEXT NULL,
    cover_image     VARCHAR(255) NULL,
    status          ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL COMMENT 'мягкое удаление (корзина)',
    UNIQUE KEY uq_projects_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Галерея изображений проекта
CREATE TABLE IF NOT EXISTS project_images (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id      INT UNSIGNED NOT NULL,
    file_path       VARCHAR(255) NOT NULL,
    caption         VARCHAR(255) NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_project_images_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Кастомные поля проекта (произвольные пары ключ-значение: заказчик, год, площадь и т.д.)
CREATE TABLE IF NOT EXISTS project_fields (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id      INT UNSIGNED NOT NULL,
    field_key       VARCHAR(100) NOT NULL,
    field_value     TEXT NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_project_fields_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Команда
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS team_members (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(190) NOT NULL,
    position        VARCHAR(190) NULL,
    photo           VARCHAR(255) NULL,
    email           VARCHAR(190) NULL,
    phone           VARCHAR(60) NULL,
    socials_json    JSON NULL COMMENT '{"facebook": "...", "instagram": "...", "telegram": "..."}',
    status          ENUM('draft', 'published') NOT NULL DEFAULT 'published',
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Конструктор форм обратной связи
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS forms (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(190) NOT NULL,
    slug            VARCHAR(190) NOT NULL,
    fields_json     JSON NOT NULL COMMENT '[{"name":"phone","label":"Телефон","type":"tel","required":true}, ...]',
    notify_email    VARCHAR(190) NULL,
    success_message VARCHAR(500) NULL DEFAULT 'Спасибо! Ваша заявка отправлена.',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_forms_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Заявки, отправленные через формы
CREATE TABLE IF NOT EXISTS form_submissions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id         INT UNSIGNED NOT NULL,
    data_json       JSON NOT NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(500) NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_form_submissions_form (form_id, created_at),
    CONSTRAINT fk_form_submissions_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Глобальные настройки сайта (логотип, цвета, шрифты, контакты, счётчики)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`           VARCHAR(100) NOT NULL,
    `value`         LONGTEXT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (`key`, `value`) VALUES
    ('site_name', 'ArtStudio'),
    ('logo_url', ''),
    ('color_primary', '#1a1a1a'),
    ('color_accent', '#e63946'),
    ('font_family', '''Inter'', sans-serif'),
    ('contact_phone', ''),
    ('contact_email', ''),
    ('contact_address', ''),
    ('counter_codes', ''),
    ('telegram_gateway_token', ''),
    ('telegram_bot_token', ''),
    ('header_config', '{"logo_position":"left","menu_position":"right","language_switcher":{"enabled":true,"format":"code"},"social_buttons":[],"cta":{"enabled":false,"text":"","url":"","style":"filled"}}')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- ---------------------------------------------------------------------------
-- Файловый менеджер (публичные и защищённые файлы)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS files (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_name   VARCHAR(255) NOT NULL,
    stored_name     VARCHAR(255) NOT NULL COMMENT 'имя файла на диске (случайное, без user input)',
    mime_type       VARCHAR(120) NOT NULL,
    size            BIGINT UNSIGNED NOT NULL,
    access_type     ENUM('public', 'protected') NOT NULL DEFAULT 'public',
    access_token    VARCHAR(64) NULL COMMENT 'токен для доступа к protected-файлу без сессии',
    download_count  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by     INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_files_access_type (access_type),
    CONSTRAINT fk_files_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Пункты меню шапки (конструктор меню)
-- lang: код языка пункта; пустой ('') = показывать во всех языках.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS menu_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lang            VARCHAR(8) NOT NULL DEFAULT '' COMMENT 'код языка или пусто для всех',
    title           VARCHAR(190) NOT NULL,
    icon_svg        TEXT NULL COMMENT 'инлайновая SVG-иконка пункта',
    is_divider      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'пункт-разделитель меню',
    url_type        ENUM('page', 'news_index', 'custom') NOT NULL DEFAULT 'custom',
    url_value       VARCHAR(500) NULL COMMENT 'slug страницы или произвольный URL',
    parent_id       INT UNSIGNED NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_menu_items_lang (lang, sort_order),
    CONSTRAINT fk_menu_items_parent FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Модульные боковые виджеты (Sidebar Engine)
-- sidebar: в какую колонку; lang: пусто = все языки, иначе конкретный язык.
-- data: JSON-настройки виджета (зависят от type).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS widgets (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sidebar         ENUM('left', 'right') NOT NULL,
    type            VARCHAR(60) NOT NULL COMMENT 'latest_news, contacts, custom_html, projects_list, team_list',
    title           VARCHAR(190) NULL COMMENT 'заголовок виджета на сайте',
    lang            VARCHAR(8) NOT NULL DEFAULT '' COMMENT 'код языка или пусто для всех',
    data            JSON NOT NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_widgets_sidebar (sidebar, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Очередь исходящих писем (обрабатывается CLI-воркером по Cron)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mail_queue (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_email        VARCHAR(190) NOT NULL,
    to_name         VARCHAR(190) NULL,
    subject         VARCHAR(255) NOT NULL,
    body            LONGTEXT NOT NULL,
    status          ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts        INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL,
    last_error      VARCHAR(500) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at         DATETIME NULL,
    KEY idx_mail_queue_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Безопасность (Блок 11): сброс пароля, backup-коды 2FA, реестр сессий.
-- Все токены/коды хранятся как SHA-256 хеши; сравнение через hash_equals.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64)     NOT NULL COMMENT 'sha256(token)',
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_resets_hash (token_hash),
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS backup_codes (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    code_hash   CHAR(64)     NOT NULL COMMENT 'sha256(code)',
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_backup_codes_user (user_id),
    CONSTRAINT fk_backup_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    sid_hash     CHAR(64)     NOT NULL COMMENT 'sha256(session_id)',
    ip_address   VARCHAR(45)  NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_sessions_sid (sid_hash),
    KEY idx_user_sessions_user (user_id),
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Очередь авто-публикаций в соцсети (этап 13, обрабатывается CLI-воркером)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS social_posts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    news_id     INT UNSIGNED NOT NULL,
    network     ENUM('facebook','linkedin','instagram') NOT NULL,
    status      ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts    INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    remote_id   VARCHAR(190) NULL COMMENT 'id опубликованного поста в сети',
    last_error  VARCHAR(500) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at     DATETIME NULL,
    UNIQUE KEY uq_social_posts_news_network (news_id, network),
    KEY idx_social_posts_status (status, created_at),
    CONSTRAINT fk_social_posts_news FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Исходящие вебхуки (этап 16.2)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS webhooks (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type  VARCHAR(60)  NOT NULL,
    url         VARCHAR(500) NOT NULL,
    secret      VARCHAR(190) NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_webhooks_event (event_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    webhook_id    BIGINT UNSIGNED NOT NULL,
    event_type    VARCHAR(60)  NOT NULL,
    payload_json  LONGTEXT     NOT NULL,
    status        ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts      INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until  DATETIME     NULL,
    response_code INT          NULL,
    last_error    VARCHAR(500) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at       DATETIME     NULL,
    KEY idx_webhook_deliveries_status (status, created_at),
    KEY idx_webhook_deliveries_hook (webhook_id, created_at),
    CONSTRAINT fk_webhook_deliveries_hook FOREIGN KEY (webhook_id) REFERENCES webhooks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Библиотека шаблонов блоков (сниппеты, этап 16.1)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS block_snippets (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(190) NOT NULL,
    blocks_json LONGTEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Журнал действий администраторов (аудит): кто, что (метод + путь), когда,
-- с какого IP. Пишется центрально для всех изменяющих запросов /admin.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    username   VARCHAR(100) NOT NULL DEFAULT '',
    method     VARCHAR(8) NOT NULL DEFAULT 'POST',
    path       VARCHAR(255) NOT NULL,
    ip         VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_user (user_id, created_at),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Менеджер 301/302-редиректов: переезд со старого сайта без потери ссылок
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS redirects (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_path   VARCHAR(255) NOT NULL,
    to_url      VARCHAR(500) NOT NULL,
    code        SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    hits        INT UNSIGNED NOT NULL DEFAULT 0,
    last_hit_at DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_redirects_from (from_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Фотоальбомы: галереи изображений с обложкой (/albums)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS photo_albums (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255) NOT NULL,
    slug         VARCHAR(255) NOT NULL,
    description  TEXT NULL,
    cover_url    VARCHAR(500) NOT NULL DEFAULT '',
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_albums_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_album_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    album_id   INT UNSIGNED NOT NULL,
    image_url  VARCHAR(500) NOT NULL,
    caption    VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_album_images (album_id, sort_order, id),
    CONSTRAINT fk_album_images FOREIGN KEY (album_id) REFERENCES photo_albums (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Подписчики email-дайджеста новостей
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscribers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(190) NOT NULL,
    token      VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_subscribers_email (email),
    UNIQUE KEY uniq_subscribers_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 404-трекер: кандидаты в 301-редиректы (страница «Редиректы»)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS not_found_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path         VARCHAR(255) NOT NULL,
    hits         INT UNSIGNED NOT NULL DEFAULT 1,
    last_referer VARCHAR(500) NULL,
    first_hit_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_hit_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_not_found_path (path),
    KEY idx_not_found_hits (hits)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Конструктор произвольных типов контента (этап 16.4)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS content_types (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(60)  NOT NULL,
    name             VARCHAR(190) NOT NULL,
    description      VARCHAR(255) NOT NULL DEFAULT '',
    has_translations TINYINT(1)   NOT NULL DEFAULT 0,
    is_public        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_content_types_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_type_fields (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(60)  NOT NULL,
    label       VARCHAR(190) NOT NULL,
    field_type  ENUM('text','textarea','number','date','image','file','relation') NOT NULL DEFAULT 'text',
    required    TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  INT          NOT NULL DEFAULT 0,
    options     LONGTEXT     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_content_type_fields_type (type_id, sort_order),
    CONSTRAINT fk_content_type_fields_type FOREIGN KEY (type_id) REFERENCES content_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_entries (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) NOT NULL,
    status      ENUM('draft','published') NOT NULL DEFAULT 'draft',
    data        LONGTEXT     NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  DATETIME     NULL,
    UNIQUE KEY uq_content_entries_slug (type_id, slug),
    KEY idx_content_entries_type (type_id, status),
    CONSTRAINT fk_content_entries_type FOREIGN KEY (type_id) REFERENCES content_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_entry_translations (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id    BIGINT UNSIGNED NOT NULL,
    lang        VARCHAR(8)   NOT NULL,
    title       VARCHAR(255) NULL,
    data        LONGTEXT     NULL,
    UNIQUE KEY uq_content_entry_translations (entry_id, lang),
    CONSTRAINT fk_content_entry_translations_entry FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Стартовые публичные типы контента государственного сайта (редактируемы)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO content_types (slug, name, description, has_translations, is_public, created_at) VALUES
    ('documenty', 'Документы', 'Официальные документы, приказы и постановления', 1, 1, NOW()),
    ('vakansii',  'Вакансии',  'Открытые вакансии организации', 1, 1, NOW()),
    ('tendery',   'Тендеры',   'Актуальные тендеры и закупки', 1, 1, NOW());

INSERT INTO content_type_fields (type_id, name, label, field_type, required, sort_order, created_at)
SELECT t.id, f.name, f.label, f.field_type, f.required, f.sort_order, NOW()
FROM content_types t
JOIN (
    SELECT 'documenty' AS slug, 'doc_number' AS name, 'Номер документа' AS label, 'text' AS field_type, 0 AS required, 0 AS sort_order
    UNION ALL SELECT 'documenty', 'doc_date',  'Дата',              'date',     0, 1
    UNION ALL SELECT 'documenty', 'category',  'Категория',         'text',     0, 2
    UNION ALL SELECT 'documenty', 'summary',   'Краткое описание',  'textarea', 0, 3
    UNION ALL SELECT 'documenty', 'file',      'Файл документа',    'file',     1, 4
    UNION ALL SELECT 'vakansii',  'department','Отдел',             'text',     0, 0
    UNION ALL SELECT 'vakansii',  'salary',    'Зарплата',          'text',     0, 1
    UNION ALL SELECT 'vakansii',  'deadline',  'Приём заявок до',   'date',     0, 2
    UNION ALL SELECT 'vakansii',  'requirements','Требования',      'textarea', 0, 3
    UNION ALL SELECT 'vakansii',  'duties',    'Обязанности',       'textarea', 0, 4
    UNION ALL SELECT 'tendery',   'tender_number','Номер тендера',  'text',     0, 0
    UNION ALL SELECT 'tendery',   'budget',    'Бюджет',            'text',     0, 1
    UNION ALL SELECT 'tendery',   'start_date','Дата публикации',   'date',     0, 2
    UNION ALL SELECT 'tendery',   'deadline',  'Приём заявок до',   'date',     0, 3
    UNION ALL SELECT 'tendery',   'summary',   'Описание',          'textarea', 0, 4
    UNION ALL SELECT 'tendery',   'file',      'Тендерная документация', 'file', 0, 5
) f ON f.slug = t.slug
WHERE NOT EXISTS (SELECT 1 FROM content_type_fields x WHERE x.type_id = t.id);

-- Календарь мероприятий: тип «Мероприятия» (страница /calendar)
INSERT IGNORE INTO content_types (slug, name, description, has_translations, is_public, created_at) VALUES
    ('meropriyatiya', 'Мероприятия', 'Календарь событий и мероприятий организации', 1, 1, NOW());

INSERT INTO content_type_fields (type_id, name, label, field_type, required, sort_order, created_at)
SELECT t.id, f.name, f.label, f.field_type, f.required, f.sort_order, NOW()
FROM content_types t
JOIN (
    SELECT 'meropriyatiya' AS slug, 'event_date' AS name, 'Дата проведения' AS label, 'date' AS field_type, 1 AS required, 0 AS sort_order
    UNION ALL SELECT 'meropriyatiya', 'event_time', 'Время',            'text',     0, 1
    UNION ALL SELECT 'meropriyatiya', 'location',   'Место проведения', 'text',     0, 2
    UNION ALL SELECT 'meropriyatiya', 'summary',    'Описание',         'textarea', 0, 3
) f ON f.slug = t.slug
WHERE NOT EXISTS (SELECT 1 FROM content_type_fields x WHERE x.type_id = t.id);

-- ---------------------------------------------------------------------------
-- Защищённое файловое хранилище (репозиторий) с собственной авторизацией
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS repo_users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(60)  NOT NULL,
    full_name       VARCHAR(190) NOT NULL DEFAULT '',
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    totp_secret     VARCHAR(64)  NULL,
    totp_enabled    TINYINT(1)   NOT NULL DEFAULT 0,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_repo_users_username (username),
    UNIQUE KEY uq_repo_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repo_files (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    category        VARCHAR(120) NOT NULL DEFAULT '',
    stored_name     VARCHAR(255) NOT NULL COMMENT 'случайное имя на диске (без user input)',
    original_name   VARCHAR(255) NOT NULL,
    mime_type       VARCHAR(120) NOT NULL,
    size            BIGINT UNSIGNED NOT NULL,
    download_count  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by     INT UNSIGNED NULL COMMENT 'id администратора-загрузчика (users.id)',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_repo_files_category (category),
    KEY idx_repo_files_created (created_at),
    CONSTRAINT fk_repo_files_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Стартовая главная страница (hero + быстрые ссылки + последние новости)
-- ---------------------------------------------------------------------------
INSERT INTO pages (title, slug, status, is_home, layout_type, created_at)
SELECT 'Главная', 'home', 'published', 1, 'no_sidebar', NOW()
WHERE NOT EXISTS (SELECT 1 FROM pages WHERE is_home = 1);

SET @home := (SELECT id FROM pages WHERE slug = 'home' AND is_home = 1 ORDER BY id ASC LIMIT 1);
SET @seed := IF(@home IS NOT NULL AND (SELECT COUNT(*) FROM blocks WHERE page_id = @home) = 0, 1, 0);

INSERT INTO blocks (page_id, lang, type, title, data, sort_order, is_active, created_at)
SELECT @home, 'ru', 'cta', 'Hero',
    '{"title":"Официальный сайт организации","text":"Актуальная информация, документы, новости и услуги в одном месте.","button_text":"Последние новости","button_url":"/news","_spacing":"max"}',
    1, 1, NOW()
FROM DUAL WHERE @seed = 1;

INSERT INTO blocks (page_id, lang, type, title, data, sort_order, is_active, created_at)
SELECT @home, 'ru', 'columns', 'Быстрые ссылки', '{"columns":3,"gap":"medium","_spacing":"premium"}', 2, 1, NOW()
FROM DUAL WHERE @seed = 1;
SET @cols := IF(@seed = 1, LAST_INSERT_ID(), NULL);

INSERT INTO blocks (page_id, parent_block_id, column_index, lang, type, title, data, sort_order, is_active, created_at)
SELECT @home, @cols, 0, 'ru', 'cta', NULL, '{"title":"Документы","text":"Приказы, постановления и официальные документы.","button_text":"Открыть раздел","button_url":"/catalog/documenty"}', 1, 1, NOW()
FROM DUAL WHERE @seed = 1 AND @cols IS NOT NULL;
INSERT INTO blocks (page_id, parent_block_id, column_index, lang, type, title, data, sort_order, is_active, created_at)
SELECT @home, @cols, 1, 'ru', 'cta', NULL, '{"title":"Вакансии","text":"Открытые вакансии и условия работы.","button_text":"Открыть раздел","button_url":"/catalog/vakansii"}', 1, 1, NOW()
FROM DUAL WHERE @seed = 1 AND @cols IS NOT NULL;
INSERT INTO blocks (page_id, parent_block_id, column_index, lang, type, title, data, sort_order, is_active, created_at)
SELECT @home, @cols, 2, 'ru', 'cta', NULL, '{"title":"Тендеры","text":"Актуальные тендеры и закупки.","button_text":"Открыть раздел","button_url":"/catalog/tendery"}', 1, 1, NOW()
FROM DUAL WHERE @seed = 1 AND @cols IS NOT NULL;

INSERT INTO blocks (page_id, lang, type, title, data, sort_order, is_active, created_at)
SELECT @home, 'ru', 'news_latest', 'Последние новости', '{"title":"Последние новости","limit":3,"_spacing":"premium"}', 3, 1, NOW()
FROM DUAL WHERE @seed = 1;

-- ---------------------------------------------------------------------------
-- Применённые миграции (для CLI database/migrate.php)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS migrations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename        VARCHAR(255) NOT NULL,
    applied_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_migrations_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Этот schema.sql уже содержит структуру всех существующих миграций, поэтому
-- для свежей установки помечаем их как применённые — database/migrate.php не
-- будет пытаться накатить их повторно. (Старые установки, созданные на схеме
-- этапов 1–2, накатят их через migrate.php.)
INSERT INTO migrations (filename) VALUES
    ('2026_07_05_block5_multilang_header_widgets.sql'),
    ('2026_07_05_soft_deletes.sql'),
    ('2026_07_05_mail_queue.sql'),
    ('2026_07_05_security_block11.sql'),
    ('2026_07_05_news_media.sql'),
    ('2026_07_05_social_posts.sql'),
    ('2026_07_06_block_snippets.sql'),
    ('2026_07_06_webhooks.sql'),
    ('2026_07_06_content_types.sql'),
    ('2026_07_06_block_revisions.sql'),
    ('2026_07_06_block_columns.sql'),
    ('2026_07_06_page_landing.sql'),
    ('2026_07_06_file_repository.sql'),
    ('2026_07_06_content_frontend.sql'),
    ('2026_07_07_block_active.sql'),
    ('2026_07_07_home_page.sql'),
    ('2026_07_08_telegram_gateway_2fa.sql'),
    ('2026_07_08_telegram_bot_login.sql'),
    ('2026_07_08_audit_log.sql'),
    ('2026_07_08_redirects.sql'),
    ('2026_07_08_events_calendar.sql'),
    ('2026_07_08_photo_albums.sql'),
    ('2026_07_08_subscribers.sql'),
    ('2026_07_08_queue_locks.sql'),
    ('2026_07_08_not_found_log.sql'),
    ('2026_07_09_menu_icons_dividers.sql'),
    ('2026_07_09_news_detail_extras.sql'),
    ('2026_07_09_news_premium_layout.sql')
ON DUPLICATE KEY UPDATE filename = filename;

SET FOREIGN_KEY_CHECKS = 1;
