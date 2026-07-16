<?php

use App\Core\Csrf;

/** @var string|null $error */
/** @var string|null $notice */
/** @var array{totp:bool, telegram:bool} $channels */
$channels = $channels ?? ['totp' => true, 'telegram' => false];
$hint = match (true) {
    $channels['totp'] && $channels['telegram'] => 'Введите 6-значный код из приложения-аутентификатора или код, отправленный в Telegram.',
    $channels['telegram'] => 'Введите 6-значный код, отправленный в ваш Telegram.',
    default => 'Введите 6-значный код из приложения-аутентификатора.',
};
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Двухфакторная аутентификация — Защищённое хранилище</title>
    <link rel="stylesheet" href="/assets/css/repo.css">
</head>
<body class="repo-auth">
<div class="repo-auth__card">
    <?php $repoLogo = trim((string) \App\Models\Setting::get('repo_logo', '')); ?>
    <div class="repo-auth__brand">
        <?php if ($repoLogo !== ''): ?>
            <img src="<?= htmlspecialchars($repoLogo, ENT_QUOTES) ?>" alt="" class="repo-auth__logo">
        <?php else: ?>
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <?php endif; ?>
        <span>Подтверждение входа</span>
    </div>
    <p class="repo-auth__sub"><?= htmlspecialchars($hint, ENT_QUOTES) ?></p>

    <?php if (!empty($error)): ?>
        <div class="repo-alert repo-alert--error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if (!empty($notice)): ?>
        <div class="repo-alert repo-alert--success"><?= htmlspecialchars((string) $notice, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <form method="post" action="/repo/login/2fa">
        <?= Csrf::field() ?>
        <div class="repo-field">
            <label for="code">Код подтверждения</label>
            <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7" required autofocus>
        </div>
        <button type="submit" class="repo-btn">Подтвердить</button>
    </form>
    <?php if ($channels['telegram']): ?>
        <form method="post" action="/repo/login/2fa/resend" style="margin-top:12px;">
            <?= Csrf::field() ?>
            <button type="submit" class="repo-btn repo-btn--ghost">Отправить код ещё раз</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
