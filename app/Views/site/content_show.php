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

$crumbs = [
    ['label' => t('Главная'), 'url' => Locale::url('/')],
    ['label' => (string) $type['name'], 'url' => Locale::url('catalog/' . $type['slug'])],
    ['label' => (string) $entry['title']],
];
require __DIR__ . '/_crumbs.php';

// Длинные поля (текст/изображение) — в основную колонку, короткие и файлы —
// в боковую карточку «Сведения».
$mainTypes = ['textarea', 'image'];
$mainFields = array_values(array_filter($fields, static fn ($f) => in_array($f['field_type'], $mainTypes, true)));
$sideFields = array_values(array_filter($fields, static fn ($f) => !in_array($f['field_type'], $mainTypes, true)));
?>
<article class="catdetail">
    <header class="catdetail__head">
        <span class="catdetail__eyebrow"><?= htmlspecialchars((string) $type['name'], ENT_QUOTES) ?></span>
        <h1 class="catdetail__title"><?= htmlspecialchars((string) $entry['title'], ENT_QUOTES) ?></h1>
        <time class="catdetail__date"><?= htmlspecialchars(t('Опубликовано'), ENT_QUOTES) ?> <?= htmlspecialchars(date('d.m.Y', strtotime((string) $entry['created_at'])), ENT_QUOTES) ?></time>
    </header>

    <div class="catdetail__grid">
        <div class="catdetail__body">
            <?php $hasMain = false; ?>
            <?php foreach ($mainFields as $f): ?>
                <?php $val = ContentFields::displayValue($f, $entry['data'][$f['name']] ?? null); ?>
                <?php if ($val === '') { continue; } $hasMain = true; ?>
                <section class="catdetail__section">
                    <h2 class="catdetail__subtitle"><?= htmlspecialchars((string) $f['label'], ENT_QUOTES) ?></h2>
                    <div class="catdetail__text"><?= $val ?></div>
                </section>
            <?php endforeach; ?>
            <?php if (!$hasMain): ?><p class="catdetail__empty"><?= htmlspecialchars(t('Описание не заполнено.'), ENT_QUOTES) ?></p><?php endif; ?>
        </div>

        <aside class="catdetail__aside">
            <div class="catdetail__card">
                <h2 class="catdetail__card-title"><?= htmlspecialchars(t('Сведения'), ENT_QUOTES) ?></h2>
                <dl class="catdetail__facts">
                    <?php foreach ($sideFields as $f): ?>
                        <?php
                        $val = ContentFields::displayValue($f, $entry['data'][$f['name']] ?? null);
                        if ($val === '' || $f['field_type'] === 'file') {
                            continue;
                        }
                        ?>
                        <div class="catdetail__fact">
                            <dt><?= htmlspecialchars((string) $f['label'], ENT_QUOTES) ?></dt>
                            <dd><?= $val ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
                <?php foreach ($sideFields as $f): ?>
                    <?php if ($f['field_type'] === 'file' && !empty($entry['data'][$f['name']])): ?>
                        <a class="catdetail__download" href="<?= htmlspecialchars((string) $entry['data'][$f['name']], ENT_QUOTES) ?>" target="_blank" rel="noopener" download>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16" aria-hidden="true"><path d="M12 4v11m0 0 4-4m-4 4-4-4"/><path d="M5 19h14"/></svg>
                            <?= htmlspecialchars((string) $f['label'], ENT_QUOTES) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a class="catdetail__back" href="<?= htmlspecialchars(Locale::url('catalog/' . $type['slug']), ENT_QUOTES) ?>">← <?= htmlspecialchars(t('Ко всем записям раздела'), ENT_QUOTES) ?></a>
            </div>
        </aside>
    </div>
</article>
<?php // Schema.org: хлебные крошки; для мероприятий — карточка события. ?>
<?php
$schemaBase = \App\Core\AppUrl::base();
$schemaUrl = static fn (string $p): string => $schemaBase . \App\Core\Locale::url($p);
echo \App\Core\SchemaOrg::render(\App\Core\SchemaOrg::breadcrumbs([
    [t('Главная'), $schemaUrl('/')],
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
