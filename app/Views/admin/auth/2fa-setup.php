<?php

use App\Core\Csrf;

/** @var string|null $error */
/** @var string $secret */
?>
<!DOCTYPE html>
<html lang="ru">
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
        Откройте приложение Google Authenticator или Яндекс Ключ, выберите
        «Добавить аккаунт» → «Ввести ключ вручную» и введите ключ ниже
        (имя аккаунта: ArtStudio CMS).
    </p>
    <?php if (!empty($error)): ?>
        <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if (!empty($secret)): ?>
        <div class="totp-secret">
            <label>Секретный ключ</label>
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
