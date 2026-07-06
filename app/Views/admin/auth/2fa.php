<?php

use App\Core\Csrf;

/** @var string|null $error */
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Двухфакторная аутентификация</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>Код подтверждения</h1>
    <p class="auth-hint">Введите 6-значный код из Google Authenticator или Яндекс Ключ,
       либо один из резервных кодов (формат xxxx-xxxx).</p>
    <?php if (!empty($error)): ?>
        <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <form method="post" action="/admin/login/2fa">
        <?= Csrf::field() ?>
        <label for="code">Код подтверждения или резервный код</label>
        <input type="text" id="code" name="code" maxlength="20" autocomplete="one-time-code" autocapitalize="off" spellcheck="false" required autofocus>
        <button type="submit">Подтвердить</button>
    </form>
</div>
</body>
</html>
