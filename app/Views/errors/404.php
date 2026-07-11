<?php

use App\Core\Database;
use App\Core\Locale;
use App\Core\Search;

// 404-трекер: этот шаблон — единственная общая точка всех 404 (роутер,
// страницы, новости, каталог). Запись не мешает отдаче страницы.
if (class_exists(\App\Models\NotFoundLog::class) && Database::isConnected()) {
    \App\Models\NotFoundLog::record();
}

$hasLocale = class_exists(Locale::class);
$lang = $hasLocale ? Locale::current() : 'ru';
$dbOn = Database::isConnected();

$L = [
    'ru' => ['Страница не найдена', 'Возможно, страница была перемещена или удалена. Попробуйте поиск или разделы ниже.', 'На главную', 'Поиск по сайту', 'Найти', 'Возможно, вы искали', 'Последние новости', 'Разделы сайта'],
    'uz' => ['Sahifa topilmadi', 'Sahifa koʻchirilgan yoki oʻchirilgan boʻlishi mumkin. Qidiruv yoki quyidagi boʻlimlardan foydalaning.', 'Bosh sahifaga', 'Sayt boʻyicha qidiruv', 'Qidirish', 'Balki siz izlagansiz', 'Soʻnggi yangiliklar', 'Sayt boʻlimlari'],
    'en' => ['Page not found', 'The page may have been moved or removed. Try searching or the sections below.', 'Home', 'Search the site', 'Search', 'You may be looking for', 'Latest news', 'Site sections'],
];
[$title, $text, $homeLabel, $searchTitle, $searchBtn, $didYou, $latestT, $sectionsT] = $L[$lang] ?? $L['ru'];
$homeUrl = $hasLocale ? Locale::url('/') : '/';
$searchUrl = $hasLocale ? Locale::url('search') : '/search';

// Умный разбор: из «мусорного» URL достаём догадку и ищем близкое.
$reqPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$seg = urldecode(basename($reqPath));
$guess = trim((string) preg_replace('/\.(html?|php)$/i', '', (string) preg_replace('/[-_]+/', ' ', $seg)));

$suggest = [];
$latest = [];
$sections = [];
if ($dbOn) {
    try {
        if (mb_strlen($guess) >= 2 && class_exists(Search::class)) {
            $suggest = array_slice(Search::site($guess, 6), 0, 5);
        }
    } catch (\Throwable) {
    }
    try {
        if (class_exists(\App\Models\News::class)) {
            foreach (\App\Models\News::published(4, 0, $lang) as $n) {
                $latest[] = ['title' => (string) $n['title'], 'url' => Locale::url('news/' . $n['slug'], $lang)];
            }
        }
    } catch (\Throwable) {
    }
    try {
        if (class_exists(\App\Models\MenuItem::class)) {
            foreach (\App\Models\MenuItem::activeForLang($lang) as $mi) {
                if (!empty($mi['is_divider']) || (int) ($mi['parent_id'] ?? 0) !== 0) {
                    continue;
                }
                $sections[] = ['title' => (string) $mi['title'], 'url' => \App\Models\MenuItem::resolveUrl($mi, $lang)];
                if (count($sections) >= 8) {
                    break;
                }
            }
        }
    } catch (\Throwable) {
    }
}

$e = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>404 — <?= $e($title) ?></title>
<style>
    :root { --navy: #173a63; --teal: #17999b; --ink: #1f2b3d; --muted: #667182; --bg: #f4f6f9; --line: #e5e9f0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'PT Sans', 'Segoe UI', Roboto, Arial, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.6; }
    .wrap { max-width: 760px; margin: 0 auto; padding: 64px 20px 80px; }
    .code { font-family: 'PT Serif', Georgia, serif; font-size: 88px; line-height: 1; font-weight: 700; color: var(--navy); margin: 0; }
    h1 { font-family: 'PT Serif', Georgia, serif; font-size: 26px; margin: 8px 0 6px; color: var(--navy); }
    .lead { color: var(--muted); margin: 0 0 28px; }
    form.s404 { display: flex; gap: 8px; margin: 0 0 32px; }
    form.s404 input { flex: 1; padding: 12px 14px; border: 1px solid var(--line); border-radius: 8px; font-size: 16px; }
    form.s404 button { padding: 12px 20px; border: 0; border-radius: 8px; background: var(--teal); color: #fff; font-size: 16px; cursor: pointer; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }
    @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
    .card h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin: 0 0 12px; }
    .card ul { list-style: none; padding: 0; margin: 0; }
    .card li { margin: 0 0 10px; }
    a { color: var(--teal); text-decoration: none; }
    a:hover { text-decoration: underline; }
    .home { display: inline-block; margin-top: 8px; padding: 11px 22px; background: var(--navy); color: #fff; border-radius: 8px; }
    .home:hover { text-decoration: none; opacity: .92; }
    .suggest { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 20px 22px; margin: 0 0 32px; }
    .suggest h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin: 0 0 12px; }
    .suggest ul { list-style: none; padding: 0; margin: 0; }
    .suggest li { margin: 0 0 10px; }
    .suggest .t { color: var(--muted); font-size: 13px; }
</style>
</head>
<body>
<div class="wrap">
    <p class="code">404</p>
    <h1><?= $e($title) ?></h1>
    <p class="lead"><?= $e($text) ?></p>

    <form class="s404" action="<?= $e($searchUrl) ?>" method="get" role="search" aria-label="<?= $e($searchTitle) ?>">
        <input type="search" name="q" value="<?= $e($guess) ?>" placeholder="<?= $e($searchTitle) ?>…" aria-label="<?= $e($searchTitle) ?>">
        <button type="submit"><?= $e($searchBtn) ?></button>
    </form>

    <?php if (!empty($suggest)): ?>
        <div class="suggest">
            <h2><?= $e($didYou) ?></h2>
            <ul>
                <?php foreach ($suggest as $s): ?>
                    <li><a href="<?= $e($s['url']) ?>"><?= $e($s['title']) ?></a> <span class="t">— <?= $e($s['type']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid">
        <?php if (!empty($latest)): ?>
            <div class="card">
                <h2><?= $e($latestT) ?></h2>
                <ul>
                    <?php foreach ($latest as $n): ?><li><a href="<?= $e($n['url']) ?>"><?= $e($n['title']) ?></a></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($sections)): ?>
            <div class="card">
                <h2><?= $e($sectionsT) ?></h2>
                <ul>
                    <?php foreach ($sections as $s): ?><li><a href="<?= $e($s['url']) ?>"><?= $e($s['title']) ?></a></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <p style="margin-top:32px;"><a class="home" href="<?= $e($homeUrl) ?>"><?= $e($homeLabel) ?></a></p>
</div>
</body>
</html>
