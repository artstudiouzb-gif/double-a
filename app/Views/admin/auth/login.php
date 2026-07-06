<?php

use App\Core\Csrf;

/** @var string|null $error */
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход в панель управления</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>Вход в панель управления</h1>
    <?php if (!empty($error)): ?>
        <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <form method="post" action="/admin/login">
        <?= Csrf::field() ?>
        <label for="username">Логин</label>
        <input type="text" id="username" name="username" autocomplete="username" required autofocus>
        <label for="password">Пароль</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="submit">Войти</button>
    </form>
    <p style="margin-top:16px; text-align:center;"><a href="/admin/forgot">Забыли пароль?</a></p>
</div>
</body>
</html>
