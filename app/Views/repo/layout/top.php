<?php

use App\Core\Csrf;
use App\Core\Flash;

/** @var string $pageTitle */
/** @var array|null $repoUser */
$repoName = htmlspecialchars((string) \App\Models\Setting::get('site_name', 'Файловый портал'), ENT_QUOTES);
$repoLogo = trim((string) \App\Models\Setting::get('repo_logo', ''));

// Версия для слабовидящих: состояние из cookie (общая с основным сайтом).
$a11ySchemes = ['cw', 'wc', 'bb'];
$a11ySizes = ['m', 'l', 'xl'];
$a11yParts = explode(':', (string) ($_COOKIE['a11y'] ?? ''));
$a11y = [
    'on' => in_array($a11yParts[0] ?? '', $a11ySchemes, true),
    'scheme' => in_array($a11yParts[0] ?? '', $a11ySchemes, true) ? $a11yParts[0] : 'cw',
    'size' => in_array($a11yParts[1] ?? '', $a11ySizes, true) ? $a11yParts[1] : 'm',
    'images' => ($a11yParts[2] ?? '') === 'off' ? 'off' : 'on',
];
?><!doctype html>
<html lang="ru"<?= $a11y['on'] ? ' data-a11y="1" data-a11y-scheme="' . htmlspecialchars($a11y['scheme'], ENT_QUOTES) . '" data-a11y-size="' . htmlspecialchars($a11y['size'], ENT_QUOTES) . '" data-a11y-images="' . htmlspecialchars($a11y['images'], ENT_QUOTES) . '"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle ?? 'Файловый портал', ENT_QUOTES) ?> — <?= $repoName ?></title>
    <link rel="stylesheet" href="/assets/css/repo.css">
    <link rel="stylesheet" href="/assets/css/a11y.css">
</head>
<body>
<header class="repo-topbar">
    <div class="repo-topbar__brand">
        <?php if ($repoLogo !== ''): ?>
            <img src="<?= htmlspecialchars($repoLogo, ENT_QUOTES) ?>" alt="<?= $repoName ?>" class="repo-topbar__logo">
        <?php else: ?>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.4 7.7 8 10 4.6-2.3 8-5 8-10V6l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg>
        <?php endif; ?>
        <span>Защищённое хранилище</span>
    </div>
    <nav>
        <button type="button" class="a11y-toggle" aria-label="Версия для слабовидящих" title="Версия для слабовидящих" aria-controls="a11y-panel" aria-expanded="<?= $a11y['on'] ? 'true' : 'false' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
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
<div class="a11y-panel<?= $a11y['on'] ? ' is-open' : '' ?>" id="a11y-panel" role="region" aria-label="Настройки версии для слабовидящих">
    <div class="a11y-panel__group">
        <b>Цвет:</b>
        <button type="button" data-a11y-set="scheme:cw" title="Чёрным по белому">Ч</button>
        <button type="button" data-a11y-set="scheme:wc" title="Белым по чёрному">Б</button>
        <button type="button" data-a11y-set="scheme:bb" title="Тёмно-синим по голубому">С</button>
    </div>
    <div class="a11y-panel__group">
        <b>Размер:</b>
        <button type="button" class="a11y-panel__size-a1" data-a11y-set="size:m" title="Обычный">А</button>
        <button type="button" class="a11y-panel__size-a2" data-a11y-set="size:l" title="Крупный">А</button>
        <button type="button" class="a11y-panel__size-a3" data-a11y-set="size:xl" title="Очень крупный">А</button>
    </div>
    <div class="a11y-panel__group">
        <b>Изображения:</b>
        <button type="button" data-a11y-set="images:on" title="Показывать">Вкл</button>
        <button type="button" data-a11y-set="images:off" title="Скрыть">Выкл</button>
    </div>
    <a href="#" class="a11y-panel__off">Обычная версия</a>
</div>
<main class="repo-main">
    <?php foreach (Flash::pull() as $flash): ?>
        <div class="repo-alert repo-alert--<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></div>
    <?php endforeach; ?>
