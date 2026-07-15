<?php

$pageTitle = 'Дашборд';
$activeNav = 'dashboard';
require __DIR__ . '/layout/header.php';

/** @var array $user */
/** @var array $counts */
/** @var array<string, int> $chartData */
/** @var array<int, array<string, mixed>> $recentLogs */
?>
<section class="admin-welcome" aria-labelledby="admin-welcome-title">
    <div>
        <h2 id="admin-welcome-title">Добро пожаловать, <?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?></h2>
        <p>Управляйте содержимым сайта и быстро переходите к основным действиям.</p>
    </div>
    <div class="admin-welcome__actions">
        <a href="/admin/news/create" class="btn btn--primary">Добавить новость</a>
        <a href="/admin/pages/create" class="btn">Добавить страницу</a>
        <a href="/" target="_blank" rel="noopener" class="btn">Открыть сайт</a>
    </div>
</section>

<div class="stat-grid">
    <a href="/admin/news" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['news'] ?></span>
        <span class="stat-card__label">Новостей</span>
    </a>
    <a href="/admin/pages" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['pages'] ?></span>
        <span class="stat-card__label">Страниц</span>
    </a>
    <a href="/admin/projects" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['projects'] ?></span>
        <span class="stat-card__label">Проектов</span>
    </a>
    <a href="/admin/team" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['team'] ?></span>
        <span class="stat-card__label">Сотрудников</span>
    </a>
    <a href="/admin/forms" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['forms'] ?></span>
        <span class="stat-card__label">Форм</span>
    </a>
    <a href="/admin/forms" class="stat-card<?= $counts['submissions_unread'] > 0 ? ' stat-card--highlight' : '' ?>">
        <span class="stat-card__value"><?= (int) $counts['submissions_unread'] ?></span>
        <span class="stat-card__label">Непрочитанных заявок</span>
    </a>
    <a href="/admin/files" class="stat-card">
        <span class="stat-card__value"><?= (int) $counts['files'] ?></span>
        <span class="stat-card__label">Файлов</span>
    </a>
</div>

<?php
$maxVal = max(1, ...array_values($chartData));
$width = 500;
$height = 220;
$padding = 30;
$chartWidth = $width - 2 * $padding;
$chartHeight = $height - 2 * $padding;

$points = [];
$xStep = count($chartData) > 1 ? $chartWidth / (count($chartData) - 1) : $chartWidth;
$i = 0;
foreach ($chartData as $date => $count) {
    $x = $padding + $i * $xStep;
    $y = $padding + $chartHeight - ($count / $maxVal) * $chartHeight;
    $points[] = "$x,$y";
    $i++;
}
$pointsStr = implode(' ', $points);
$fillPointsStr = "$padding," . ($height - $padding) . " $pointsStr " . ($width - $padding) . "," . ($height - $padding);
?>
<div class="dashboard-grid">
    <div class="form-card">
        <h3 style="margin-top:0;">Активность заявок</h3>
        <p class="form-hint">Число заполненных форм обратной связи за последние 7 дней.</p>
        <div style="max-width:100%;margin-top:12px;">
            <svg viewBox="0 0 500 220" width="100%" height="100%" style="overflow:visible;">
                <defs>
                    <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--admin-accent)" stop-opacity="0.3"></stop>
                        <stop offset="100%" stop-color="var(--admin-accent)" stop-opacity="0"></stop>
                    </linearGradient>
                </defs>
                <!-- Grid Lines -->
                <?php for ($grid = 0; $grid <= 4; $grid++): ?>
                    <?php $gy = $padding + ($chartHeight / 4) * $grid; ?>
                    <line x1="<?= $padding ?>" y1="<?= $gy ?>" x2="<?= $width - $padding ?>" y2="<?= $gy ?>" stroke="var(--admin-border)" stroke-width="1" stroke-dasharray="4,4"></line>
                <?php endfor; ?>
                <!-- Filled Area -->
                <polygon points="<?= $fillPointsStr ?>" fill="url(#chartGrad)"></polygon>
                <!-- Line -->
                <polyline points="<?= $pointsStr ?>" fill="none" stroke="var(--admin-accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>
                <!-- Data Points -->
                <?php $i = 0; foreach ($chartData as $date => $count): ?>
                    <?php 
                    $parts = explode(',', $points[$i]); 
                    $cx = (float) $parts[0];
                    $cy = (float) $parts[1];
                    ?>
                    <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="5" fill="var(--admin-surface)" stroke="var(--admin-accent)" stroke-width="2"></circle>
                    <text x="<?= $cx ?>" y="<?= $cy - 10.0 ?>" font-size="10" font-weight="700" fill="var(--admin-text)" text-anchor="middle"><?= $count ?></text>
                <?php $i++; endforeach; ?>
                <!-- X Labels -->
                <?php $i = 0; foreach ($chartData as $date => $count): ?>
                    <?php 
                    $parts = explode(',', $points[$i]); 
                    $cx = $parts[0];
                    $label = date('d.m', strtotime($date));
                    ?>
                    <text x="<?= $cx ?>" y="<?= $height - $padding + 18 ?>" font-size="10" fill="var(--admin-muted)" text-anchor="middle"><?= $label ?></text>
                <?php $i++; endforeach; ?>
            </svg>
        </div>
    </div>

    <div class="form-card">
        <h3 style="margin-top:0;">Журнал действий</h3>
        <p class="form-hint">Последние действия администраторов в панели управления.</p>
        <div class="activity-feed" style="margin-top:16px;">
            <?php if (empty($recentLogs)): ?>
                <p class="form-hint" style="margin:0;">Действий пока нет.</p>
            <?php else: ?>
                <?php foreach ($recentLogs as $log): ?>
                    <div class="activity-item">
                        <div class="activity-item__meta">
                            <strong><?= htmlspecialchars((string) ($log['username'] ?? 'System'), ENT_QUOTES) ?></strong>
                            <span class="activity-item__time"><?= date('H:i d.m.Y', strtotime((string) $log['created_at'])) ?></span>
                        </div>
                        <div class="activity-item__desc">
                            <?php $m = strtoupper((string) ($log['method'] ?? '')); ?>
                            <span class="activity-item__badge activity-item__badge--<?= strtolower($m) ?>"><?= htmlspecialchars($m, ENT_QUOTES) ?></span>
                            <code><?= htmlspecialchars((string) ($log['path'] ?? ''), ENT_QUOTES) ?></code>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
