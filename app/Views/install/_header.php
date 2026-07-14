<?php
/** @var string $step */
/** @var string $heading */
$step = $step ?? '';
$heading = $heading ?? 'Установка';
$steps = ['1' => 'Окружение', '2' => 'База данных', '3' => 'Сайт', '4' => 'Администратор'];
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Установка ArtStudio CMS</title>
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.install-steps { display: flex; gap: 8px; justify-content: center; margin-bottom: 24px; flex-wrap: wrap; }
.install-steps span { padding: 6px 12px; border-radius: 999px; font-size: 13px; background: #eee; color: #888; }
.install-steps span.is-active { background: #4361ee; color: #fff; }
.install-check { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #eee; font-size: 14px; }
.install-check__mark { font-weight: 700; }
.install-check__mark.ok { color: #1e7e34; }
.install-check__mark.fail { color: #e63946; }
.install-check__hint { display: block; font-size: 12px; color: #999; }
.install-card .form-actions { align-items: stretch; gap: 10px; margin-top: 24px; }
.install-card .form-actions .btn {
    flex: 1 1 180px; min-height: 44px; margin: 0; justify-content: center;
    padding: 10px 16px; border-radius: 6px;
}
.install-card .btn--primary,
.install-card .btn--primary:visited { background: var(--admin-accent); border-color: var(--admin-accent); color: #fff; }
.install-card .btn--primary:hover { background: var(--admin-accent-hover); border-color: var(--admin-accent-hover); color: #fff; }
.install-card .btn:active { transform: translateY(1px); }
.install-card .btn:disabled,
.install-card .btn[aria-disabled="true"] { opacity: .5; cursor: not-allowed; transform: none; }
@media (max-width: 520px) {
    .install-card { padding: 28px 22px; }
    .install-card .form-actions { flex-direction: column; }
    .install-card .form-actions .btn { flex-basis: auto; width: 100%; }
}
</style>
</head>
<body class="auth-page">
<div class="auth-card auth-card--wide install-card">
    <h1>Установка ArtStudio CMS</h1>
    <div class="install-steps">
        <?php foreach ($steps as $n => $label): ?>
            <span class="<?= $n === $step ? 'is-active' : '' ?>"><?= $n ?>. <?= htmlspecialchars($label, ENT_QUOTES) ?></span>
        <?php endforeach; ?>
    </div>
