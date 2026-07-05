# Безопасность ArtStudio CMS

Документ фиксирует ключевые меры безопасности и результаты внутреннего аудита
(Блок 11). Вся криптография — на нативных функциях PHP, без Composer и сторонних
библиотек.

## Аутентификация и сессии

- **Пароли**: `password_hash(PASSWORD_BCRYPT, cost=12)`, проверка `password_verify`.
- **Политика паролей** (`App\Core\PasswordPolicy`): минимум 10 символов, ≥2 групп
  символов, запрет логина/e-mail внутри пароля, сверка со словарём топ-10000
  скомпрометированных паролей (`database/data/weak-passwords.txt`, локально).
- **Обязательная 2FA (TOTP, RFC 6238)**: секрет генерируется только через
  `random_bytes`, сравнение кодов — `hash_equals` с окном ±1 шаг.
- **Backup-коды 2FA**: пул из 10 одноразовых кодов, в БД только `sha256`-хеш,
  сверка `hash_equals`, каждый код одноразовый (`used_at`).
- **Сброс пароля**: одноразовый токен (`random_bytes(32)`), в БД только `sha256`,
  TTL 30 минут, single-use; при успешной смене все сессии пользователя
  завершаются.
- **Реестр сессий** (`user_sessions`): хранится `sha256(session_id)`. Даёт список
  устройств и мгновенный серверный отзыв — сессия действительна, только пока её
  строка присутствует. «Выйти на всех устройствах» и смена пароля затирают строки.
- **Привязка сессии** к фингерпринту (User-Agent + /16 подсеть), `hash_equals`.
- **Cookies**: `HttpOnly`, `SameSite=Lax`, `Secure` при HTTPS, `use_strict_mode`.
- **Rate limiting**: вход, 2FA, сброс пароля, отправка форм, перебор
  download-токенов, частота чанков — превышение отдаёт `429`, попытки
  фиксируются в `login_attempts` и логах.

## Криптографический аудит (Задача 48)

| Требование | Реализация | Статус |
|---|---|---|
| TLS `verify_peer`/`verify_peer_name` в SMTP | `App\Core\Mailer::connect` — жёстко `true`, `allow_self_signed=false`, `peer_name=host` | ✅ |
| TOTP-секрет только из `random_bytes` | `App\Core\TOTP::generateSecret` | ✅ |
| Токены/коды сравниваются `hash_equals` | TOTP, Csrf, download-токен, backup-коды, reset-токены | ✅ |
| Пароли — bcrypt | `App\Models\User` | ✅ |
| Токены/коды в БД — только хеши | reset/backup/session — `sha256`; пароли — bcrypt | ✅ |

## XSS и контент

- **Экранирование**: весь вывод пользовательских данных — `htmlspecialchars(ENT_QUOTES)`.
- **Блок «HTML-код»**: сырой HTML разрешён только супер-администратору. Для роли
  `editor` контент проходит строгий allowlist-санитайзер
  (`App\Core\HtmlSanitizer`): вырезаются `<script>`, обработчики `on*`,
  `javascript:`/опасные `data:`-URI, запрещённые теги разворачиваются.
- **SVG-загрузки**: санитизация через `DOMDocument` (`Uploader::sanitizeSvgString`),
  отдача с `Content-Disposition: attachment` и жёстким CSP-sandbox.
- **CSS блоков**: изоляция через `CssScoper` (префикс `#block-{id}`), вырезание
  `@import`, `javascript:`, `expression()`.
- **Ссылки** (CTA, меню): валидация схемы через `App\Core\UrlGuard::isSafeLink`
  (только http/https/mailto/tel/относительные) — `javascript:` в `href` отсекается.

## Заголовки безопасности (Задача 51)

Выставляются в `App\Core\SecurityHeaders::send` максимально рано в `bootstrap.php`,
попадают на **все** ответы, включая 404/500/503 и fail-safe:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Cross-Origin-Opener-Policy: same-origin`
- `X-Permitted-Cross-Domain-Policies: none`
- `Strict-Transport-Security` — только по HTTPS
- `Content-Security-Policy` — для `/admin/*` и `/install/*`
  (`default-src 'self'`, `object-src 'none'`, `frame-ancestors 'self'` и т.д.).

## Аудит SSRF и открытых редиректов (Задача 55)

- **Серверные исходящие запросы по пользовательскому URL отсутствуют.** Поле
  «изображение по ссылке» (`ImageField`) только сохраняет строку URL, не
  скачивая её. Прямого вектора SSRF в коде нет.
- **`App\Core\UrlGuard::isSafeRemote`** предоставлен как обязательный шлюз на
  случай появления серверных запросов (webhooks, импорт, автопубликация):
  запрещает не-http(s), резолвит хост и блокирует приватные/loopback/link-local
  диапазоны (в т.ч. `169.254.169.254`).
- **Открытые редиректы**: все `Location` — внутренние литералы; редирект «назад»
  после отправки формы (`Site\FormController::redirectBack`) допускает только
  относительные пути (`/...`).

## CSRF

- Токен на сессию (`bin2hex(random_bytes(32))`), проверка `hash_equals` на всех
  POST-запросах (`App\Core\Csrf`). Honeypot + метка времени на публичных формах.

## Защита файлов

- Приватные файлы отдаются `public/download.php` по токену (`hash_equals`),
  с проверкой пути через `realpath` (защита от path traversal), анти-перебором
  токенов и `X-Content-Type-Options: nosniff`.

## Тесты

Нативный тест-раннер `php tests/run.php` (без Composer) покрывает: рендер блока с
битым/пустым JSON, изоляцию `CssScoper`, санитайзер `HtmlSanitizer` (ACL editor),
политику паролей, `UrlGuard`, консистентность и применимость миграций.
