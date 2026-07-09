<?php

use App\Core\ContentFields;
use App\Core\Locale;

/** @var array $type */
/** @var array $fields */
/** @var array $entry */

$metaTitle = (string) $entry['title'];
$metaDescription = '';
// Мета-описание из первого текстового поля, если есть.
foreach ($fields as $f) {
    if (in_array($f['field_type'], ['textarea', 'text'], true)) {
        $raw = (string) ($entry['data'][$f['name']] ?? '');
        if ($raw !== '') {
            $metaDescription = mb_substr(trim(strip_tags($raw)), 0, 200);
            break;
        }
    }
}
require __DIR__ . '/_header.php';
?>
<article class="content-detail">
    <nav class="content-crumbs" aria-label="Хлебные крошки">
        <a href="<?= htmlspecialchars(Locale::url('/'), ENT_QUOTES) ?>">Главная</a>
        <span>/</span>
        <a href="<?= htmlspecialchars(Locale::url('catalog/' . $type['slug']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $type['name'], ENT_QUOTES) ?></a>
        <span>/</span>
        <span><?= htmlspecialchars((string) $entry['title'], ENT_QUOTES) ?></span>
    </nav>
    <h1 class="content-detail__title"><?= htmlspecialchars((string) $entry['title'], ENT_QUOTES) ?></h1>
    <time class="content-detail__date"><?= htmlspecialchars(date('d.m.Y', strtotime((string) $entry['created_at'])), ENT_QUOTES) ?></time>

    <?php
    // Раскладка «С боковой панелью»: длинный контент (текст/изображение) — в
    // основную колонку, короткие поля и файлы — в мета-панель. Раскладка «В
    // одну колонку» показывает всё подряд (CSS схлопывает грид). Драйвер —
    // класс design-detail-* на <body>.
    $mainTypes = ['textarea', 'image'];
    $renderRow = static function (array $f, string $val, array $entry): string {
        $out = '<div class="content-detail__row content-detail__row--' . htmlspecialchars((string) $f['field_type'], ENT_QUOTES) . '">';
        $out .= '<dt>' . htmlspecialchars((string) $f['label'], ENT_QUOTES) . '</dt><dd>';
        if ($f['field_type'] === 'file') {
            $out .= '<a class="content-detail__download" href="' . htmlspecialchars((string) $entry['data'][$f['name']], ENT_QUOTES) . '" target="_blank" rel="noopener" download>📎 Скачать</a>';
        } else {
            $out .= $val;
        }
        return $out . '</dd></div>';
    };
    $mainHtml = '';
    $asideHtml = '';
    foreach ($fields as $f) {
        $val = ContentFields::displayValue($f, $entry['data'][$f['name']] ?? null);
        if ($val === '') {
            continue;
        }
        if (in_array($f['field_type'], $mainTypes, true)) {
            $mainHtml .= $renderRow($f, $val, $entry);
        } else {
            $asideHtml .= $renderRow($f, $val, $entry);
        }
    }
    ?>
    <div class="content-detail__grid">
        <div class="content-detail__body">
            <?php if ($mainHtml !== ''): ?><dl class="content-detail__fields"><?= $mainHtml ?></dl><?php endif; ?>
            <?php if ($mainHtml === '' && $asideHtml === ''): ?><p class="content-detail__empty">Нет данных.</p><?php endif; ?>
        </div>
        <?php if ($asideHtml !== ''): ?>
            <aside class="content-detail__aside"><dl class="content-detail__fields"><?= $asideHtml ?></dl></aside>
        <?php endif; ?>
    </div>
</article>
<?php // Schema.org: хлебные крошки; для мероприятий — карточка события. ?>
<?php
$schemaBase = rtrim((string) \App\Core\Config::get('app.url', ''), '/');
$schemaUrl = static fn (string $p): string => $schemaBase . \App\Core\Locale::url($p);
echo \App\Core\SchemaOrg::render(\App\Core\SchemaOrg::breadcrumbs([
    ['Главная', $schemaUrl('/')],
    [(string) $type['name'], $schemaUrl('catalog/' . $type['slug'])],
    [(string) $entry['title'], ''],
])), "\n";
if ((string) $type['slug'] === 'meropriyatiya') {
    echo \App\Core\SchemaOrg::render(\App\Core\SchemaOrg::event(
        (string) $entry['title'],
        $schemaUrl('catalog/' . $type['slug'] . '/' . $entry['slug']),
        (string) ($entry['data']['event_date'] ?? ''),
        (string) ($entry['data']['location'] ?? ''),
        strip_tags((string) ($entry['data']['summary'] ?? ''))
    )), "\n";
}
?>
<?php require __DIR__ . '/_footer.php'; ?>
