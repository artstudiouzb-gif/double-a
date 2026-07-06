<?php

use App\Core\Csrf;
use App\Core\QrCode;

/** @var string|null $error */
/** @var string $secret */
/** @var string $uri */

$qrSvg = '';
if (!empty($uri)) {
    try {
        $qrSvg = QrCode::svg($uri, 4, 4);
    } catch (\Throwable $e) {
        $qrSvg = ''; // при неудаче остаётся ручной ввод ключа
    }
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Настройка двухфакторной аутентификации</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="auth-page">
<div class="auth-card auth-card--wide">
    <h1>Подключите 2FA</h1>
    <p class="auth-hint">
        Двухфакторная аутентификация обязательна для входа в панель управления.
        Откройте Google Authenticator или Яндекс Ключ и отсканируйте QR-код ниже.
        Если сканировать неудобно — выберите «Ввести ключ вручную» и введите
        секретный ключ (имя аккаунта: ArtStudio CMS).
    </p>
    <?php if (!empty($error)): ?>
        <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if ($qrSvg !== ''): ?>
        <div class="totp-qr"><?= $qrSvg ?></div>
    <?php endif; ?>
    <?php if (!empty($secret)): ?>
        <div class="totp-secret">
            <label>Секретный ключ (для ручного ввода)</label>
            <code><?= htmlspecialchars(chunk_split($secret, 4, ' '), ENT_QUOTES) ?></code>
        </div>
    <?php endif; ?>
    <form method="post" action="/admin/login/2fa-setup">
        <?= Csrf::field() ?>
        <label for="code">Код подтверждения из приложения</label>
        <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required autofocus>
        <button type="submit">Включить 2FA и войти</button>
    </form>
</div>
</body>
</html>
