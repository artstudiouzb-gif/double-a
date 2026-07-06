<?php

use App\Core\Csrf;
use App\Core\Flash;

/** @var string $pageTitle */
/** @var array|null $repoUser */
$repoName = htmlspecialchars((string) \App\Models\Setting::get('site_name', 'Файловый портал'), ENT_QUOTES);
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle ?? 'Файловый портал', ENT_QUOTES) ?> — <?= $repoName ?></title>
    <link rel="stylesheet" href="/assets/css/repo.css">
</head>
<body>
<header class="repo-topbar">
    <div class="repo-topbar__brand">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.4 7.7 8 10 4.6-2.3 8-5 8-10V6l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg>
        <span>Защищённое хранилище</span>
    </div>
    <nav>
        <?php if (!empty($repoUser)): ?>
            <span class="repo-topbar__user"><?= htmlspecialchars((string) ($repoUser['full_name'] ?: $repoUser['username']), ENT_QUOTES) ?></span>
            <a href="/repo">Файлы</a>
            <a href="/repo/security">Безопасность</a>
            <form method="post" action="/repo/logout" style="display:inline;margin:0;">
                <?= Csrf::field() ?>
                <button type="submit">Выйти</button>
            </form>
        <?php endif; ?>
    </nav>
</header>
<main class="repo-main">
    <?php foreach (Flash::pull() as $flash): ?>
        <div class="repo-alert repo-alert--<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></div>
    <?php endforeach; ?>
