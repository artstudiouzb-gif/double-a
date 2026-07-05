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
    status          ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    published_at    DATETIME NULL,
    author_id       INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_news_slug (slug),
    KEY idx_news_status_published (status, published_at),
    CONSTRAINT fk_news_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Страницы (статические, собираются из блоков)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL,
    meta_title      VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    status          ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    is_home         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Гарантия, что домашней может быть только одна страница обеспечивается на уровне приложения
-- (перед установкой is_home = 1 остальные страницы сбрасываются в транзакции).

-- ---------------------------------------------------------------------------
-- Блоки конструктора страниц (Page Builder)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id         INT UNSIGNED NOT NULL,
    type            VARCHAR(60) NOT NULL COMMENT 'text, slider, advantages, cta, gallery, form, html',
    title           VARCHAR(255) NULL COMMENT 'внутреннее название блока для админки',
    data            JSON NOT NULL COMMENT 'структурированные данные блока',
    custom_css      TEXT NULL COMMENT 'CSS блока, изолируется при рендере через #block-{id}',
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_blocks_page (page_id, sort_order),
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
    ('counter_codes', '')
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

SET FOREIGN_KEY_CHECKS = 1;
