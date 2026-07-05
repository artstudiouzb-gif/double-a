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
    content         LONGTEXT NULL,
    image           VARCHAR(255) NULL,
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
    lang            VARCHAR(8) NOT NULL DEFAULT '' COMMENT 'код языка стека блоков',
    type            VARCHAR(60) NOT NULL COMMENT 'text, slider, advantages, cta, gallery, form, html',
    title           VARCHAR(255) NULL COMMENT 'внутреннее название блока для админки',
    data            JSON NOT NULL COMMENT 'структурированные данные блока',
    custom_css      TEXT NULL COMMENT 'CSS блока, изолируется при рендере через #block-{id}',
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_blocks_page (page_id, lang, sort_order),
    CONSTRAINT fk_blocks_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
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
    ('2026_07_05_security_block11.sql')
ON DUPLICATE KEY UPDATE filename = filename;

SET FOREIGN_KEY_CHECKS = 1;
