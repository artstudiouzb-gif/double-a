<?php

use App\Core\Csrf;

/** @var string|null $error */
/** @var bool $sent */
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Восстановление пароля</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>Восстановление пароля</h1>
    <?php if (!empty($error)): ?>
        <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <?php if (!empty($sent)): ?>
        <div class="alert alert--success">
            Если такой e-mail зарегистрирован, мы отправили на него ссылку для сброса пароля.
            Ссылка действительна 30 минут.
        </div>
        <p><a href="/admin/login">← Вернуться ко входу</a></p>
    <?php else: ?>
        <p class="form-hint">Укажите e-mail учётной записи — вышлем ссылку для сброса пароля.</p>
        <form method="post" action="/admin/forgot">
            <?= Csrf::field() ?>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" autocomplete="email" required autofocus>
            <button type="submit">Отправить ссылку</button>
        </form>
        <p style="margin-top:16px;"><a href="/admin/login">← Вернуться ко входу</a></p>
    <?php endif; ?>
</div>
</body>
</html>
