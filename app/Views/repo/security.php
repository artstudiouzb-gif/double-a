<?php

use App\Core\Csrf;
use App\Core\QrCode;

/** @var array $repoUser */
/** @var string|null $setupSecret */
/** @var string|null $otpauthUri */
/** @var string|null $error */
$pageTitle = 'Безопасность';
$enabled = (int) ($repoUser['totp_enabled'] ?? 0) === 1;
require __DIR__ . '/layout/top.php';
?>
<h1 class="repo-page-title">Безопасность аккаунта</h1>

<?php if (!empty($error)): ?>
    <div class="repo-alert repo-alert--error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="repo-card">
    <h2 style="margin-top:0;">Двухфакторная аутентификация (2FA)</h2>
    <?php if ($enabled): ?>
        <p><span class="repo-badge repo-badge--ok">Включена</span> Вход в портал защищён одноразовым кодом из приложения-аутентификатора.</p>
        <form method="post" action="/repo/security/2fa/disable" onsubmit="return confirm('Отключить двухфакторную аутентификацию?');">
            <?= Csrf::field() ?>
            <button type="submit" class="repo-btn repo-btn--danger">Отключить 2FA</button>
        </form>
    <?php else: ?>
        <p><span class="repo-badge repo-badge--muted">Выключена</span> Рекомендуем включить для дополнительной защиты доступа.</p>
        <?php
        // Страховка: при слишком длинном URI (например, очень длинный логин)
        // показываем только ручной ключ вместо ошибки 500.
        $qrSvg = '';
        try {
            $qrSvg = QrCode::svg((string) $otpauthUri, 4);
        } catch (\Throwable) {
        }
        ?>
        <div class="repo-qr">
            <?php if ($qrSvg !== ''): ?><div class="repo-qr__code"><?= $qrSvg ?></div><?php endif; ?>
            <div>
                <p>1. <?= $qrSvg !== '' ? 'Отсканируйте QR-код приложением (Google Authenticator, Aegis, 1Password и т.п.).' : 'Добавьте ключ в приложение-аутентификатор (Google Authenticator, Aegis, 1Password и т.п.).' ?></p>
                <p>2. <?= $qrSvg !== '' ? 'Или введите ключ вручную:' : 'Ключ для ручного ввода:' ?> <span class="repo-secret"><?= htmlspecialchars((string) $setupSecret, ENT_QUOTES) ?></span></p>
                <p>3. Введите текущий 6-значный код для подтверждения:</p>
                <form method="post" action="/repo/security/2fa/enable" style="max-width:280px;">
                    <?= Csrf::field() ?>
                    <div class="repo-field" style="margin-bottom:12px;">
                        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7" placeholder="000000" required>
                    </div>
                    <button type="submit" class="repo-btn">Включить 2FA</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="repo-card">
    <h2 style="margin-top:0;">Вход с подтверждением в Telegram</h2>
    <?php if (empty($telegramConfigured)): ?>
        <p class="repo-hint">Недоступно: на сайте не настроен Telegram-бот. Обратитесь к администратору.</p>
    <?php elseif (!empty($telegramLinked)): ?>
        <p><span class="repo-badge repo-badge--ok">Подключено</span> При входе в портал одноразовый код будет отправляться в ваш Telegram.</p>
        <form method="post" action="/repo/security/telegram/disable" onsubmit="return confirm('Отвязать Telegram? Вход по коду из Telegram отключится.');">
            <?= Csrf::field() ?>
            <button type="submit" class="repo-btn repo-btn--danger">Отвязать Telegram</button>
        </form>
    <?php else: ?>
        <p><span class="repo-badge repo-badge--muted">Не подключено</span> Одноразовые коды входа можно получать в Telegram — вместо или вместе с приложением-аутентификатором.</p>
        <ol style="margin:10px 0 14px;padding-left:20px;line-height:1.7;">
            <li>
                <?php if (!empty($telegramBotUsername)): ?>
                    Откройте бота <a href="https://t.me/<?= htmlspecialchars((string) $telegramBotUsername, ENT_QUOTES) ?>?start=<?= htmlspecialchars((string) $telegramLinkCode, ENT_QUOTES) ?>" target="_blank" rel="noopener">@<?= htmlspecialchars((string) $telegramBotUsername, ENT_QUOTES) ?></a> и нажмите «Start».
                <?php else: ?>
                    Откройте Telegram-бота сайта и отправьте ему сообщение.
                <?php endif; ?>
            </li>
            <li>Отправьте боту код привязки: <span class="repo-secret"><?= htmlspecialchars((string) ($telegramLinkCode ?? ''), ENT_QUOTES) ?></span></li>
            <li>Вернитесь сюда и нажмите «Проверить привязку».</li>
        </ol>
        <form method="post" action="/repo/security/telegram/verify">
            <?= Csrf::field() ?>
            <button type="submit" class="repo-btn">Проверить привязку</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/layout/bottom.php'; ?>
