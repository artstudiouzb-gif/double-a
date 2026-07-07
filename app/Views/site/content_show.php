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

    <dl class="content-detail__fields">
        <?php foreach ($fields as $f): ?>
            <?php $val = ContentFields::displayValue($f, $entry['data'][$f['name']] ?? null); ?>
            <?php if ($val === '') { continue; } ?>
            <div class="content-detail__row content-detail__row--<?= htmlspecialchars((string) $f['field_type'], ENT_QUOTES) ?>">
                <dt><?= htmlspecialchars((string) $f['label'], ENT_QUOTES) ?></dt>
                <dd>
                    <?php if ($f['field_type'] === 'file'): ?>
                        <a class="content-detail__download" href="<?= htmlspecialchars((string) $entry['data'][$f['name']], ENT_QUOTES) ?>" target="_blank" rel="noopener" download>📎 Скачать</a>
                    <?php else: ?>
                        <?= $val ?>
                    <?php endif; ?>
                </dd>
            </div>
        <?php endforeach; ?>
    </dl>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
