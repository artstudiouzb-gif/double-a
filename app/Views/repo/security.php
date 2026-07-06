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
        <div class="repo-qr">
            <div class="repo-qr__code"><?= QrCode::svg((string) $otpauthUri, 4) ?></div>
            <div>
                <p>1. Отсканируйте QR-код приложением (Google Authenticator, Aegis, 1Password и т.п.).</p>
                <p>2. Или введите ключ вручную: <span class="repo-secret"><?= htmlspecialchars((string) $setupSecret, ENT_QUOTES) ?></span></p>
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
<?php require __DIR__ . '/layout/bottom.php'; ?>
