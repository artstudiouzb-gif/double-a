<?php

use App\Core\Csrf;

/** @var string|null $error */
$repoName = htmlspecialchars((string) \App\Models\Setting::get('site_name', 'Файловый портал'), ENT_QUOTES);
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Вход — Защищённое хранилище</title>
    <link rel="stylesheet" href="/assets/css/repo.css">
</head>
<body class="repo-auth">
<div class="repo-auth__card">
    <div class="repo-auth__brand">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.4 7.7 8 10 4.6-2.3 8-5 8-10V6l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg>
        <span>Защищённое хранилище</span>
    </div>
    <p class="repo-auth__sub">Вход в файловый портал <?= $repoName ?></p>

    <?php if (!empty($error)): ?>
        <div class="repo-alert repo-alert--error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <form method="post" action="/repo/login">
        <?= Csrf::field() ?>
        <div class="repo-field">
            <label for="username">Логин</label>
            <input type="text" id="username" name="username" autocomplete="username" required autofocus>
        </div>
        <div class="repo-field">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="repo-btn">Войти</button>
    </form>
    <p class="repo-auth__foot">Доступ предоставляется администратором.</p>
</div>
</body>
</html>
